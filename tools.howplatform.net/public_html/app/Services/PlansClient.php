<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * عميل رفيع لقراءة صلاحيات المستخدم من منصّة Plans. غير حاجب ويبتلع الأخطاء.
 */
class PlansClient
{
    public function mode(): string
    {
        return (string) config('plans.mode', 'off');
    }

    public function enabled(): bool
    {
        return $this->mode() !== 'off'
            && config('plans.baseUrl') && config('plans.apiKey');
    }

    /** GET /entitlements?user_id= — يُعيد مصفوفة الاستجابة أو null عند التعذّر. */
    public function entitlements(int $userId): ?array
    {
        return $this->get('/entitlements', ['user_id' => $userId]);
    }

    /** GET /wallet/stats?user_id= — ملخّص النقاط (أو null). */
    public function walletStats(int $userId): ?array
    {
        return $this->get('/wallet/stats', ['user_id' => $userId]);
    }

    /** ملخّص شارة الهيدر: {points, has_plan, plan_name} — أو null (تُخفى الشارة). */
    public function badgeSummary(int $userId): ?array
    {
        $stats = $this->walletStats($userId);
        $ent   = $this->entitlements($userId);
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
        if (! $this->enabled() || (int) ($query['user_id'] ?? 1) <= 0) {
            return null;
        }
        try {
            $r = Http::withHeaders(['X-Api-Key' => config('plans.apiKey'), 'Accept' => 'application/json'])
                ->timeout((float) config('plans.timeout', 1.5))
                ->get(rtrim(config('plans.baseUrl'), '/') . $path, $query);

            return $r->ok() ? $r->json() : null;
        } catch (\Throwable $e) {
            Log::warning('PlansClient get ' . $path . ' failed: ' . $e->getMessage());
            return null;
        }
    }
}
