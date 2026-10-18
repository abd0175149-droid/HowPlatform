<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * جالب "الأدوات التعليمية" ديناميكيًا من منصّة الأدوات (tools.howplatform.net/api/tools).
 * - جلب سيرفر-سايد مع كاش (لا CORS، ولا اعتماد على توفّر tools لحظة العرض).
 * - صامد: عند تعذّر الجلب يُعيد آخر نسخة ناجحة (last-good) بدل قائمة فارغة.
 * قابل للتهيئة عبر TOOLS_API_URL في .env.
 */
class ToolsCatalog
{
    private const API_URL   = 'https://tools.howplatform.net/api/tools';
    private const FRESH_KEY = 'edu_tools_catalog_v1';        // نسخة "طازجة" قصيرة العمر
    private const GOOD_KEY   = 'edu_tools_catalog_good_v1';  // آخر نسخة ناجحة (احتياطي طويل)
    private const FRESH_TTL  = 600;                          // 10 دقائق
    private const GOOD_TTL   = 604800;                       // 7 أيام

    /**
     * @return array<int, array{name:string, description:string, icon_class:string, icon_color:string, href:string, is_featured:int}>
     */
    public static function fetch(): array
    {
        $cache = \Config\Services::cache();

        $fresh = $cache->get(self::FRESH_KEY);
        if (is_array($fresh)) {
            return $fresh;
        }

        $tools = self::fetchRemote();
        if (! empty($tools)) {
            $cache->save(self::FRESH_KEY, $tools, self::FRESH_TTL);
            $cache->save(self::GOOD_KEY, $tools, self::GOOD_TTL);
            return $tools;
        }

        // فشل الجلب → آخر نسخة ناجحة إن وُجدت
        $good = $cache->get(self::GOOD_KEY);
        return is_array($good) ? $good : [];
    }

    private static function fetchRemote(): array
    {
        try {
            $url    = getenv('TOOLS_API_URL') ?: self::API_URL;
            // نسخة غير مشتركة كي تُطبَّق مهلتنا (النسخة المشتركة قد تكون بمهلة أقصر من عميل آخر مثل PlansClient)
            $client = \Config\Services::curlrequest(
                ['timeout' => 5, 'connect_timeout' => 3],
                null,
                null,
                false
            );
            $res    = $client->get($url, ['http_errors' => false]);

            if ($res->getStatusCode() !== 200) {
                log_message('warning', 'ToolsCatalog: HTTP ' . $res->getStatusCode() . ' from ' . $url);
                return [];
            }

            $data  = json_decode((string) $res->getBody(), true);
            $items = $data['tools'] ?? $data['data'] ?? [];
            if (! is_array($items)) {
                return [];
            }

            $out = [];
            foreach ($items as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $name = trim((string) ($it['name'] ?? ''));
                $href = trim((string) ($it['href'] ?? $it['go_url'] ?? $it['url'] ?? ''));
                if ($name === '' || $href === '') {
                    continue;
                }
                $out[] = [
                    'name'        => $name,
                    'description' => trim((string) ($it['description'] ?? '')),
                    'icon_class'  => trim((string) ($it['icon_class'] ?? '')) ?: 'fas fa-wrench',
                    'icon_color'  => trim((string) ($it['icon_color'] ?? '')),
                    'href'        => $href,
                    'is_featured' => (int) ($it['is_featured'] ?? 0),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            log_message('warning', 'ToolsCatalog: fetch failed: ' . $e->getMessage());
            return [];
        }
    }
}
