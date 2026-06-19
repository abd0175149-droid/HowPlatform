<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\Cache\CacheInterface;
use Config\RemoteUsers as RemoteUsersConfig;
use Throwable;

/**
 * RemoteUsersClient
 * - يتصل بخدمة Laravel: /lookup/users/names
 * - يدعم دفعات IDs (POST JSON) + كاش محلي 10 دقائق لكل مستخدم
 * - يعيد map[id] = ['name'=>..., 'email'=>...] (قد تكون email null لو الـ API لا يعيدها)
 */
class RemoteUsersClient
{
    private RemoteUsersConfig $config;
    private CURLRequest $http;
    private CacheInterface $cache;

    /** TTL للكاش بالثواني (10 دقائق) */
    private int $ttl = 600;

    public function __construct(RemoteUsersConfig $config = null, CURLRequest $http = null, CacheInterface $cache = null)
    {
        $this->config = $config ?? config(RemoteUsersConfig::class);
        $this->http   = $http   ?? service('curlrequest', [
            'timeout'        => $this->config->timeout,
            'connectTimeout' => $this->config->connectTimeout,
            'verify'         => true, // SSL
        ]);
        $this->cache  = $cache  ?? cache();
    }

    /** جلب اسم/إيميل لمستخدم واحد (مع كاش) */
    public function getOne(int $userId): array
    {
        $userId = max(0, (int) $userId);
        if ($userId <= 0) {
            return ['name' => null, 'email' => null];
        }

        $ck = $this->cacheKey($userId);

        try {
            $cached = $this->cache->get($ck);
            if (is_array($cached)) {
                return [
                    'name'  => $cached['name']  ?? null,
                    'email' => $cached['email'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            // تجاهل أي خطأ في الكاش ولا تعطل التنفيذ
        }

        $map = $this->resolveMany([$userId]);
        return $map[$userId] ?? ['name' => null, 'email' => null];
    }

    /**
     * جلب أسماء/إيميلات لدفعة IDs (مع كاش).
     * يعيد: [id => ['name'=>..., 'email'=>...], ...]
     */
    public function resolveMany(array $ids): array
    {
        // تنظيف/توحيد
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn ($v) => $v > 0);
        if (empty($ids)) {
            return [];
        }

        $result = [];
        $misses = [];

        // 1) من الكاش
        foreach ($ids as $id) {
            $ck = $this->cacheKey($id);
            try {
                $cached = $this->cache->get($ck);
                if (is_array($cached)) {
                    $result[$id] = [
                        'name'  => $cached['name']  ?? null,
                        'email' => $cached['email'] ?? null,
                    ];
                    continue;
                }
            } catch (Throwable $e) {
                // تجاهل خطأ الكاش واستمر
            }
            $misses[] = $id;
        }

        // 2) من الـ API لما يفوتنا بالكاش
        if (! empty($misses)) {
            $fetched = $this->fetchMany($misses);

            // خزّن بالكاش وادمج
            foreach ($misses as $id) {
                $row = $fetched[$id] ?? ['name' => null, 'email' => null];

                try {
                    $this->cache->save($this->cacheKey($id), $row, $this->ttl);
                } catch (Throwable $e) {
                    // تجاهل خطأ الكاش
                }

                $result[$id] = $row;
            }
        }

        return $result;
    }

    /* ================= داخلي ================= */

    /**
     * اتصال فعلي مع الـ API (Laravel) لجلب الأسماء دفعة واحدة.
     * يدعم شكلين للرد:
     * - { data: { "123": "Name", ... } }
     * - { data: { "123": {"name":"..","email":".."}, ... } }
     */
    private function fetchMany(array $ids): array
    {
        $out = [];
        if (empty($this->config->baseUrl)) {
            foreach ($ids as $id) {
                $out[$id] = ['name' => null, 'email' => null];
            }
            return $out;
        }

        $url = rtrim($this->config->baseUrl, '/') . '/users/names';

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // رأس الـ API Key (مطلوب عبر verify.apikey)
        if ($this->config->apiKey !== '') {
            $headers[$this->config->apiKeyHeader] = $this->config->apiKey;
            // خيار بديل لو الميدلوير يقبله
            $headers['Authorization'] = 'Bearer ' . $this->config->apiKey;
        }

        try {
            $resp = $this->http->post($url, [
                'headers' => $headers,
                'json'    => ['ids' => array_values($ids)],
            ]);

            $status = $resp->getStatusCode();
            if ($status < 200 || $status >= 300) {
                foreach ($ids as $id) {
                    $out[$id] = ['name' => null, 'email' => null];
                }
                return $out;
            }

            $data = json_decode((string) $resp->getBody(), true);
            if (! is_array($data)) {
                foreach ($ids as $id) {
                    $out[$id] = ['name' => null, 'email' => null];
                }
                return $out;
            }

            $map = $data['data'] ?? [];
            foreach ($ids as $id) {
                $val = $map[(string) $id] ?? $map[$id] ?? null;

                if (is_array($val)) {
                    $name  = $val['name']  ?? null;
                    $email = $val['email'] ?? null;
                } elseif (is_string($val) || is_numeric($val)) {
                    $name  = (string) $val;
                    $email = null;
                } else {
                    $name  = null;
                    $email = null;
                }

                $out[$id] = ['name' => $name, 'email' => $email];
            }
        } catch (Throwable $e) {
            foreach ($ids as $id) {
                $out[$id] = ['name' => null, 'email' => null];
            }
        }

        return $out;
    }

    /**
     * مفاتيح كاش آمنة (بدون محارف محجوزة في CI):
     * يُسمح عادةً بـ [A-Za-z0-9_.-] — نستخدم بادئة ru_ ثم رقم الـ ID.
     */
    private function cacheKey(int $userId): string
    {
        return 'ru_' . $userId; // ← لا تستخدم ":" أو أي محارف محجوزة
    }
}
