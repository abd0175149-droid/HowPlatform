<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * عميل منصّة النقاط (Plans) للمكتبة: عدّاد التحميلات اليومية + بيانات شارة
 * الهيدر (الخطة/الرصيد). كل النداءات تفشل بأمان (null) ولا تُسقط الصفحة أبدًا.
 */
class PlansClient
{
    private string $baseUrl;
    private ?string $apiKey;
    private float $timeout;
    private string $mode;

    public function __construct()
    {
        $this->baseUrl = (string) config('plans.base_url');
        $this->apiKey  = config('plans.api_key');
        $this->timeout = (float) config('plans.timeout', 1.5);
        $this->mode    = (string) config('plans.mode', 'off');
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function enabled(): bool
    {
        return $this->mode === 'api' && !empty($this->apiKey) && $this->baseUrl !== '';
    }

    /** GET /usage — لقطة قراءة فقط: used/limit/remaining/allowed (أو null). */
    public function usageSnapshot(int $userId, string $featureKey, ?int $defaultLimit = null): ?array
    {
        if (!$this->enabled() || $userId <= 0) {
            return null;
        }
        $q = ['user_id' => $userId, 'feature_key' => $featureKey];
        if ($defaultLimit !== null) {
            $q['default_limit'] = $defaultLimit;
        }
        return $this->get('/usage', $q);
    }

    /** POST /usage/consume — استهلاك ذرّي check-and-increment (allowed=true|false أو null). */
    public function usageConsume(int $userId, string $featureKey, int $amount = 1, ?int $defaultLimit = null): ?array
    {
        if (!$this->enabled() || $userId <= 0) {
            return null;
        }
        $payload = ['user_id' => $userId, 'feature_key' => $featureKey, 'amount' => $amount];
        if ($defaultLimit !== null) {
            $payload['default_limit'] = $defaultLimit;
        }
        try {
            $res = Http::acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/usage/consume', $payload);
            if ($res->ok()) {
                return $res->json() ?: null;
            }
        } catch (\Throwable $e) {
            \Log::warning('PlansClient::usageConsume failed: ' . $e->getMessage());
        }
        return null;
    }

    /** ملخّص شارة الهيدر: {points, has_plan, plan_name} — أو null (تُخفى الشارة). */
    public function badgeSummary(int $userId): ?array
    {
        if (!$this->enabled() || $userId <= 0) {
            return null;
        }
        $stats = $this->get('/wallet/stats', ['user_id' => $userId]);
        $ent   = $this->get('/entitlements', ['user_id' => $userId]);
        if ($stats === null && $ent === null) {
            return null;
        }
        return [
            'points'    => (int) ($stats['total_points'] ?? 0),
            'has_plan'  => (bool) ($ent['has_plan'] ?? false),
            'plan_name' => $ent['plan']['name'] ?? null,
        ];
    }

    private function get(string $path, array $query = []): ?array
    {
        try {
            $res = Http::acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout($this->timeout)
                ->get($this->baseUrl . $path, $query);
            if ($res->ok()) {
                return $res->json() ?: null;
            }
        } catch (\Throwable $e) {
            \Log::warning('PlansClient::get ' . $path . ' failed: ' . $e->getMessage());
        }
        return null;
    }
}
