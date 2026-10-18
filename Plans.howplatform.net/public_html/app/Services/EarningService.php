<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\Referral;
use App\Models\ReferralCode;
use Illuminate\Support\Str;

/**
 * منطق كسب النقاط (مُرحَّل من الدبلوما):
 *  - تتابع الدخول: 5 أيام متتالية → +50، ثم تصفير. (بتوقيت عمّان)
 *  - الإحالة: إكمال ملف المدعوّ → +100 للداعي، مرّة واحدة، وبسقف 5 دعوات مُكافأة لكل داعٍ.
 * كل المنح idempotent عبر WalletService.
 */
class EarningService
{
    private const STREAK_TARGET = 5;
    private const STREAK_REWARD = 50;
    private const REFERRAL_REWARD = 100;
    /** الحد الأقصى للدعوات المُكافأة لكل داعٍ (قرار المؤسس 2026-07-03). */
    public const REFERRAL_MAX_REWARDED = 5;
    private const TZ = 'Asia/Amman';

    public function __construct(private WalletService $wallet) {}

    /** تسجيل دخول يومي + منطق التتابع. */
    public function recordLogin(int $userId, ?string $todayOverride = null): array
    {
        if ($userId <= 0) return ['ok' => false, 'error' => 'invalid_user'];

        $tz    = new \DateTimeZone(self::TZ);
        $now   = $todayOverride
            ? new \DateTimeImmutable($todayOverride, $tz)
            : new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');

        $row  = Streak::firstOrCreate(['user_id' => $userId], ['current_streak' => 0, 'last_login_date' => null]);
        $last = $row->last_login_date ? $row->last_login_date->format('Y-m-d') : null;

        if ($last === $today) {
            return ['ok' => true, 'streak' => (int) $row->current_streak, 'rewarded' => false];
        }

        $newStreak = 1;
        if ($last) {
            $yesterday = $now->modify('-1 day')->format('Y-m-d');
            if ($last === $yesterday) {
                $newStreak = ($row->current_streak + 1 > self::STREAK_TARGET) ? 1 : (int) $row->current_streak + 1;
            }
        }

        $rewarded = false;
        if ($newStreak === self::STREAK_TARGET) {
            $res = $this->wallet->earn($userId, self::STREAK_REWARD, [
                'service_client'  => 'plans',
                'idempotency_key' => 'plans:earn:streak:' . $userId . ':' . $today,
                'event_type'      => 'plans.streak.day5',
                'description'     => 'استخدام المنصة 5 أيام متتالية',
            ]);
            $rewarded = (int) ($res['code'] ?? 0) === 200;
            $row->current_streak = 0; // تصفير بعد المنح
        } else {
            $row->current_streak = $newStreak;
        }
        $row->last_login_date = $today;
        $row->save();

        return ['ok' => true, 'streak' => $rewarded ? 0 : $newStreak, 'rewarded' => $rewarded];
    }

    /** تسجيل دعوة (داعٍ ← مدعوّ) بحالة pending. */
    public function recordReferral(int $referrerId, int $referredId): array
    {
        if ($referrerId <= 0 || $referredId <= 0 || $referrerId === $referredId) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $r = Referral::firstOrCreate(
            ['referred_id' => $referredId],
            ['referrer_id' => $referrerId, 'status' => 'pending']
        );
        return ['ok' => true, 'referral_id' => (int) $r->id, 'status' => $r->status];
    }

    /** منح مكافأة الإحالة عند إكمال المدعوّ ملفّه — بسقف REFERRAL_MAX_REWARDED لكل داعٍ. */
    public function completeReferral(int $referredId): array
    {
        $r = Referral::where('referred_id', $referredId)->first();
        if (!$r)                            return ['ok' => false, 'error' => 'no_referral'];
        if ($r->status === 'rewarded')      return ['ok' => true,  'already' => true, 'referrer_id' => (int) $r->referrer_id];
        if ($r->status === 'completed')     return ['ok' => true,  'already' => true, 'capped' => true, 'referrer_id' => (int) $r->referrer_id];
        if ((int) $r->referrer_id === $referredId) return ['ok' => false, 'error' => 'self_referral'];

        // سقف المكافآت: الداعي الذي حصّل 5 دعوات مُكافأة لا يُمنح أكثر —
        // تُسجَّل الإحالة كمكتملة (completed) بلا نقاط.
        $rewardedCount = Referral::where('referrer_id', (int) $r->referrer_id)
            ->where('status', 'rewarded')
            ->count();
        if ($rewardedCount >= self::REFERRAL_MAX_REWARDED) {
            $r->status = 'completed';
            $r->save();
            return ['ok' => true, 'capped' => true, 'awarded' => 0, 'referrer_id' => (int) $r->referrer_id];
        }

        $res = $this->wallet->earn((int) $r->referrer_id, self::REFERRAL_REWARD, [
            'service_client'  => 'plans',
            'idempotency_key' => 'plans:earn:referral:' . $r->id,
            'event_type'      => 'plans.referral.completed',
            'event_ref_type'  => 'referral',
            'event_ref_id'    => (string) $r->id,
            'description'     => 'دعوة صديق',
        ]);

        if ((int) ($res['code'] ?? 0) === 200) {
            $r->status = 'rewarded';
            $r->rewarded_at = now();
            $r->save();
            return ['ok' => true, 'awarded' => self::REFERRAL_REWARD, 'referrer_id' => (int) $r->referrer_id];
        }
        return ['ok' => false, 'error' => 'award_failed'];
    }

    /** إحصاءات إحالات المستخدم: عدد الدعوات، المُكافأة منها، والنقاط المكتسبة + السقف. */
    public function referralStats(int $userId): array
    {
        if ($userId <= 0) {
            return ['total' => 0, 'rewarded' => 0, 'pending' => 0, 'earned' => 0, 'max_rewarded' => self::REFERRAL_MAX_REWARDED];
        }
        $total    = Referral::where('referrer_id', $userId)->count();
        $rewarded = Referral::where('referrer_id', $userId)->where('status', 'rewarded')->count();

        return [
            'total'        => $total,
            'rewarded'     => $rewarded,
            'pending'      => max(0, $total - $rewarded),
            'earned'       => $rewarded * self::REFERRAL_REWARD,
            'max_rewarded' => self::REFERRAL_MAX_REWARDED,
        ];
    }

    /** كود إحالة المستخدم (يُنشَأ إن لم يوجد). */
    public function referralCode(int $userId): string
    {
        $rc = ReferralCode::firstOrCreate(['user_id' => $userId], ['code' => $this->generateCode()]);
        return $rc->code;
    }

    private function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (ReferralCode::where('code', $code)->exists());
        return $code;
    }
}
