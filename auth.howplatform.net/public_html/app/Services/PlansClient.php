<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

/**
 * عميل منصّة النقاط (Plans) للواجهة الموحّدة في أوث.
 * القراءة تتم على التوازي (Http::pool) وتفشل بأمان (كل قسم غير متاح → فارغ/مخفي).
 * الكتابة (استبدال بطاقة) عبر نقطة redeem الموثوقة.
 */
class PlansClient
{
    private string $baseUrl;
    private ?string $apiKey;
    private float $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = (string) config('plans.base_url');
        $this->apiKey  = config('plans.api_key');
        $this->timeout = (float) config('plans.timeout', 1.5);
        $this->enabled = (bool) config('plans.enabled', true) && !empty($this->apiKey) && $this->baseUrl !== '';
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * كل بيانات الواجهة الموحّدة في نداء متوازٍ واحد. يُرجِع مصفوفة موحّدة،
     * وأي قسم يفشل يبقى فارغًا (fail-open). available=false لو تعذّر كل شيء.
     */
    public function profileData(int $userId): array
    {
        $empty = [
            'available'    => false,
            'points'       => 0,
            'streak'       => 0,
            'referral'     => ['code' => null, 'link' => null, 'total' => 0, 'completed' => 0, 'pending' => 0, 'rewarded' => 0, 'max' => 5],
            'has_plan'     => false,
            'plan'         => null,
            'end_at'       => null,
            'transactions' => [],
            'current_sub'  => null,
            'subscriptions'=> [],
            'plans'        => [],
            'packages'     => [],
        ];

        if (!$this->enabled || $userId <= 0) {
            return $empty;
        }

        $base = $this->baseUrl;
        $key  = $this->apiKey;
        $t    = $this->timeout;

        try {
            $r = Http::pool(fn (Pool $pool) => [
                $pool->as('stats')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/wallet/stats', ['user_id' => $userId]),
                $pool->as('ent')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/entitlements', ['user_id' => $userId]),
                $pool->as('tx')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/wallet/transactions', ['user_id' => $userId, 'limit' => 10]),
                $pool->as('subs')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/subscriptions', ['user_id' => $userId]),
                $pool->as('catalog')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/catalog'),
                // نقطة مُنشِئة: تولّد كود الإحالة إن لم يوجد (كي لا يختفي قسم الدعوة)
                $pool->as('refcode')->acceptJson()->withHeaders(['X-Api-Key' => $key])->timeout($t)
                    ->get($base . '/referral-code', ['user_id' => $userId]),
            ]);
        } catch (\Throwable $e) {
            return $empty;
        }

        $json = function ($resp): ?array {
            return ($resp instanceof Response && $resp->ok()) ? ($resp->json() ?: []) : null;
        };

        $stats   = $json($r['stats'] ?? null);
        $ent     = $json($r['ent'] ?? null);
        $tx      = $json($r['tx'] ?? null);
        $subs    = $json($r['subs'] ?? null);
        $catalog = $json($r['catalog'] ?? null);
        $refcode = $json($r['refcode'] ?? null);

        if ($stats === null && $ent === null && $tx === null && $subs === null && $catalog === null) {
            return $empty;
        }

        // الكود من النقطة المُنشِئة أولًا (مضمونة الوجود)، ثم كود الإحصاءات كاحتياط.
        $code = $refcode['code'] ?? ($stats['referral_code'] ?? null);

        return [
            'available'     => true,
            'points'        => (int) ($stats['total_points'] ?? 0),
            'streak'        => (int) ($stats['current_streak'] ?? 0),
            'referral'      => [
                'code'      => $code,
                'link'      => $code ? ('https://auth.howplatform.net/register?ref=' . $code) : null,
                'total'     => (int) ($stats['total_referrals'] ?? 0),
                'completed' => (int) ($stats['completed_referrals'] ?? 0),
                'pending'   => (int) ($stats['pending_referrals'] ?? 0),
                'rewarded'  => (int) ($stats['rewarded_referrals'] ?? 0),
                'max'       => (int) ($stats['referral_max_rewarded'] ?? 5),
            ],
            'has_plan'      => (bool) ($ent['has_plan'] ?? false),
            'plan'          => $ent['plan'] ?? null,
            'end_at'        => $ent['end_at'] ?? null,
            'transactions'  => $tx['transactions'] ?? ($tx['items'] ?? []),
            'current_sub'   => $subs['current'] ?? null,
            'subscriptions' => $subs['history'] ?? [],
            'plans'         => $catalog['plans'] ?? [],
            'packages'      => $catalog['packages'] ?? [],
        ];
    }

    /** كود إحالة → صاحبه (تحقّق التسجيل). يُرجِع user_id أو null. */
    public function resolveReferralCode(string $code): ?int
    {
        $res = $this->get('/referral-code/resolve', ['code' => $code]);
        if (is_array($res) && ($res['ok'] ?? false) && !empty($res['user_id'])) {
            return (int) $res['user_id'];
        }
        return null;
    }

    /** تسجيل إحالة جديدة (referrer→referred) مركزيًا في Plans. */
    public function recordReferral(int $referrerId, int $referredId): bool
    {
        $res = $this->postJson('/earn/referral', [
            'referrer_id' => $referrerId,
            'referred_id' => $referredId,
        ]);
        return is_array($res) && ($res['ok'] ?? false);
    }

    /** إكمال الإحالة (منح مكافأة الداعي) مركزيًا في Plans — idempotent. */
    public function completeReferral(int $referredId): bool
    {
        $res = $this->postJson('/earn/referral/complete', [
            'referred_id' => $referredId,
        ]);
        return is_array($res) && ($res['ok'] ?? false);
    }

    /** POST مساعد يفشل بأمان (null عند التعذّر). */
    private function postJson(string $path, array $payload): ?array
    {
        if (!$this->enabled) {
            return null;
        }
        try {
            $res = Http::acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(max(3.0, $this->timeout))
                ->post($this->baseUrl . $path, $payload);
            if ($res->successful()) {
                return $res->json() ?: [];
            }
        } catch (\Throwable $e) {
            // fail-open
        }
        return null;
    }

    /** استبدال بطاقة لصالح المستخدم المسجّل (كتابة). يُرجِع مصفوفة النتيجة أو خطأ اتصال. */
    public function redeemCard(int $userId, string $code): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'unavailable'];
        }

        try {
            $res = Http::acceptJson()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->timeout(max(3.0, $this->timeout))
                ->post($this->baseUrl . '/cards/redeem', [
                    'user_id' => $userId,
                    'code'    => $code,
                ]);

            $body = $res->json() ?: [];
            if (!array_key_exists('ok', $body)) {
                $body['ok'] = $res->successful();
            }
            return $body;
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'connection'];
        }
    }
}
