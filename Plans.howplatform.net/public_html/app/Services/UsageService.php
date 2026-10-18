<?php

namespace App\Services;

use App\Models\UsageCounter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * عدّاد استهلاك المزايا المحدودة بحصّة (rate-limited features).
 *
 * يحلّ الحدّ (limit) من صلاحيات المستخدم (EntitlementService.features_flat) ويعدّ الاستهلاك
 * ضمن نافذة زمنية. يطابق فلسفة المحفظة: ذرّي (قفل صفّ العدّاد)، يفشل مغلقًا عند تجاوز الحصّة.
 *
 * نموذج الحدّ:
 *   - limit = قيمة الميزة في الخطّة (مثل fateen_messages_per_day=100).
 *   - عند غياب الميزة (لا خطّة/غير مضبوطة): يُستخدم defaultLimit الذي تمرّره المنصّة (مثل 7 لفطين).
 *   - limit = null  → غير محدود (يُسمح دائمًا، ويُعدّ للتحليلات فقط).
 *
 * نافذة العدّ (period):
 *   - 'day'   → YYYY-MM-DD بتوقيت Asia/Amman (يطابق حدّ اليوم في EarningService).
 *   - 'month' → YYYY-MM بتوقيت Asia/Amman.
 */
class UsageService
{
    private const TZ = 'Asia/Amman';

    public function __construct(private EntitlementService $entitlements) {}

    /** قراءة فقط: الحالة الحالية دون أي تعديل. */
    public function snapshot(int $userId, string $featureKey, ?int $default, string $period = 'day'): array
    {
        $periodKey = $this->periodKey($period);
        $limit     = $this->resolveLimit($userId, $featureKey, $default);
        $used       = (int) (UsageCounter::where('user_id', $userId)
            ->where('feature_key', $featureKey)
            ->where('period_key', $periodKey)
            ->value('used') ?? 0);

        return $this->shape($userId, $featureKey, $periodKey, $limit, $used, $limit === null || $used < $limit);
    }

    /**
     * استهلاك ذرّي check-and-increment.
     * يفشل مغلقًا: لو (used + amount) يتجاوز الحدّ → allowed=false ولا يزيد العدّاد إطلاقًا.
     * يُعيد دائمًا مصفوفة الحالة + allowed.
     */
    public function consume(int $userId, string $featureKey, int $amount, ?int $default, string $period = 'day'): array
    {
        $amount    = max(1, $amount);
        $periodKey = $this->periodKey($period);
        $limit     = $this->resolveLimit($userId, $featureKey, $default);

        return DB::transaction(function () use ($userId, $featureKey, $periodKey, $limit, $amount) {
            // اقفل (أو أنشئ) صفّ العدّاد — يُسلسِل كل عمليات نفس (مستخدم، ميزة، نافذة).
            $row = UsageCounter::where('user_id', $userId)
                ->where('feature_key', $featureKey)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                try {
                    UsageCounter::create([
                        'user_id'     => $userId,
                        'feature_key' => $featureKey,
                        'period_key'  => $periodKey,
                        'used'        => 0,
                    ]);
                } catch (QueryException $e) {
                    // إنشاء متزامن من طلب آخر — تجاهل ثم أعد القراءة بقفل.
                }
                $row = UsageCounter::where('user_id', $userId)
                    ->where('feature_key', $featureKey)
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();
            }

            $used = (int) $row->used;

            // فشل مغلق: تجاوز الحصّة → لا تزِد العدّاد.
            if ($limit !== null && ($used + $amount) > $limit) {
                return $this->shape($userId, $featureKey, $periodKey, $limit, $used, false);
            }

            $row->used = $used + $amount;
            $row->save();

            return $this->shape($userId, $featureKey, $periodKey, $limit, (int) $row->used, true);
        }, 3); // أعد المحاولة حتى 3 مرات عند deadlock
    }

    /** يحلّ الحدّ من صلاحيات المستخدم، أو الافتراضي الذي تمرّره المنصّة، أو null (غير محدود). */
    private function resolveLimit(int $userId, string $featureKey, ?int $default): ?int
    {
        $ent  = $this->entitlements->forUser($userId);
        $flat = (array) ($ent['features_flat'] ?? []);

        if (array_key_exists($featureKey, $flat) && is_numeric($flat[$featureKey])) {
            return (int) $flat[$featureKey];
        }

        return $default; // قد يكون null = غير محدود
    }

    private function periodKey(string $period): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TZ));

        return $period === 'month'
            ? $now->format('Y-m')
            : $now->format('Y-m-d');
    }

    private function shape(int $userId, string $featureKey, string $periodKey, ?int $limit, int $used, bool $allowed): array
    {
        return [
            'ok'          => true,
            'user_id'     => $userId,
            'feature_key' => $featureKey,
            'period_key'  => $periodKey,
            'limit'       => $limit,                                   // null = غير محدود
            'used'        => $used,
            'remaining'   => $limit === null ? null : max(0, $limit - $used),
            'allowed'     => $allowed,
        ];
    }
}
