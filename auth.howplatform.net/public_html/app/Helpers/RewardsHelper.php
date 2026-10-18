<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use App\Services\PlansClient;

/**
 * RewardsHelper
 * جسر نظام الإحالات من أوث إلى منصّة النقاط المركزية (Plans).
 * كان يكتب مباشرة في قاعدة الدبلوم (تجاوز خطير) — الآن كل الكتابة عبر Plans API،
 * والقراءة المحلية بقيت فقط للتوافق مع أكواد REF القديمة (روابط دعوة منشورة سابقًا).
 */
class RewardsHelper
{
    /**
     * التحقق من صحة كود الدعوة → معرف صاحبه.
     * أولًا عبر Plans (الأكواد الجديدة)، ثم fallback للصيغة القديمة الحتمية REF+md5.
     */
    public static function validateReferralCode(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        // 1) الأكواد الجديدة: مخزّنة مركزيًا في Plans
        try {
            $userId = app(PlansClient::class)->resolveReferralCode($code);
            if ($userId !== null && $userId > 0) {
                return $userId;
            }
        } catch (\Throwable $e) {
            \Log::warning('RewardsHelper::validateReferralCode Plans resolve failed: ' . $e->getMessage());
        }

        // 2) توافق قديم: الصيغة الحتمية REF + md5 (روابط دعوة منشورة قبل الترحيل)
        if (preg_match('/^REF[A-F0-9]{8}$/i', $code)) {
            try {
                $db = DB::connection('diploma');
                $users = $db->table('diploma_users')->select('user_id')->get();
                foreach ($users as $user) {
                    $testUserId = (int) $user->user_id;
                    $hash = md5('referral_' . $testUserId . '_howplatform');
                    if ('REF' . strtoupper(substr($hash, 0, 8)) === strtoupper($code)) {
                        return $testUserId;
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('RewardsHelper::validateReferralCode legacy error: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * تسجيل دعوة جديدة — مركزيًا في Plans (idempotent: دعوة واحدة لكل مدعوّ).
     */
    public static function registerReferral(int $referrerUserId, int $referredUserId, string $code): ?int
    {
        // التحقق من عدم الاحتيال
        if ($referrerUserId === $referredUserId) {
            \Log::warning("RewardsHelper: User {$referrerUserId} tried to refer themselves");
            return null;
        }

        try {
            $ok = app(PlansClient::class)->recordReferral($referrerUserId, $referredUserId);
            if ($ok) {
                return $referredUserId; // معرف رمزي — النجاح هو المهم للمستدعي
            }
            \Log::warning("RewardsHelper::registerReferral Plans rejected referrer={$referrerUserId} referred={$referredUserId}");
        } catch (\Throwable $e) {
            \Log::error('RewardsHelper::registerReferral error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * التحقق من إكمال البروفايل ومنح مكافأة الداعي — مركزيًا في Plans (idempotent).
     */
    public static function checkAndRewardReferral(int $referredUserId): bool
    {
        try {
            // البروفايل مكتمل؟ (قراءة فقط من قاعدة الدبلوم)
            $diplomaUser = DB::connection('diploma')
                ->table('diploma_users')
                ->where('user_id', $referredUserId)
                ->first();

            if (!$diplomaUser || (int) ($diplomaUser->is_complete ?? 0) !== 1) {
                return false; // البروفايل غير مكتمل
            }

            return app(PlansClient::class)->completeReferral($referredUserId);
        } catch (\Throwable $e) {
            \Log::error('RewardsHelper::checkAndRewardReferral error: ' . $e->getMessage());
            return false;
        }
    }
}
