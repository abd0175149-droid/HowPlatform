<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LibraryMetricsService
{
    public function overview(array $range = []): array
    {
        $cacheKey = 'metrics.overview.' . md5(json_encode($range));
        return Cache::remember($cacheKey, 300, function () use ($range) {
            $base = rtrim(env('LIBRARY_BASE_URL'), '/');
            $url  = $base . '/api/metrics/overview';

            $resp = Http::timeout(5)
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => env('LIBRARY_API_KEY')])
                ->get($url, $range);

            if ($resp->ok()) {
                return $resp->json();
            }

            // fallback آمن
            return [
                'range' => ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()],
                'totals' => ['pageviews' => 0, 'unique_sessions' => 0, 'unique_ips' => 0],
                'top_pages' => [],
                'timeseries' => [],
            ];
        });
    }
}
