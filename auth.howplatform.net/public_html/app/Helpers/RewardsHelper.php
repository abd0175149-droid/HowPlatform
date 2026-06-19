<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * RewardsHelper
 * Helper Class للوصول لنظام المكافآت في Diploma من Auth
 */
class RewardsHelper
{
    /**
     * التحقق من صحة كود الدعوة
     *
     * @param string $code
     * @return int|null معرف صاحب الكود
     */
    public static function validateReferralCode(string $code): ?int
    {
        try {
            $db = DB::connection('diploma');
            
            // البحث عن كود دعوة في السجلات
            $referral = $db->table('rewards_referrals')
                ->where('referral_code', $code)
                ->first();

            if ($referral) {
                $referrerUserId = (int)$referral->referrer_user_id;
                
                // التحقق من وجود المستخدم في diploma_users
                $userExists = $db->table('diploma_users')
                    ->where('user_id', $referrerUserId)
                    ->exists();
                
                return $userExists ? $referrerUserId : null;
            }

            // إذا لم نجد سجل، نحاول البحث في جميع المستخدمين
            // الكود ثابت لكل مستخدم (REF + hash من user_id)
            $users = $db->table('diploma_users')
                ->select('user_id')
                ->get();

            foreach ($users as $user) {
                $testUserId = (int)$user->user_id;
                // استخدام نفس منطق generateReferralCode من ReferralSystem
                $hash = md5('referral_' . $testUserId . '_howplatform');
                $testCode = 'REF' . strtoupper(substr($hash, 0, 8));
                if ($testCode === $code) {
                    return $testUserId;
                }
            }

            return null;
        } catch (\Throwable $e) {
            \Log::error('RewardsHelper::validateReferralCode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * تسجيل دعوة جديدة
     *
     * @param int $referrerUserId صاحب كود الدعوة
     * @param int $referredUserId المستخدم المدعو
     * @param string $code كود الدعوة
     * @return int|null معرف السجل
     */
    public static function registerReferral(int $referrerUserId, int $referredUserId, string $code): ?int
    {
        // التحقق من عدم الاحتيال
        if ($referrerUserId === $referredUserId) {
            \Log::warning("RewardsHelper: User {$referrerUserId} tried to refer themselves");
            return null;
        }

        try {
            // التحقق من عدم وجود دعوة مسبقة
            $existing = DB::connection('diploma')
                ->table('rewards_referrals')
                ->where('referrer_user_id', $referrerUserId)
                ->where('referred_user_id', $referredUserId)
                ->first();

            if ($existing) {
                return (int)$existing->id;
            }

            // إنشاء سجل جديد
            $id = DB::connection('diploma')
                ->table('rewards_referrals')
                ->insertGetId([
                    'referrer_user_id' => $referrerUserId,
                    'referred_user_id' => $referredUserId,
                    'status'          => 'pending',
                    'referral_code'   => $code,
                    'created_at'      => Carbon::now('Asia/Amman'),
                ]);

            return $id;
        } catch (\Throwable $e) {
            \Log::error('RewardsHelper::registerReferral error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * التحقق من إكمال البروفايل ومنح المكافأة
     *
     * @param int $referredUserId معرف المستخدم المدعو
     * @return bool
     */
    public static function checkAndRewardReferral(int $referredUserId): bool
    {
        try {
            $db = DB::connection('diploma');

            // 1. التحقق من إكمال البروفايل
            $diplomaUser = $db->table('diploma_users')
                ->where('user_id', $referredUserId)
                ->first();

            if (!$diplomaUser || (int)($diplomaUser->is_complete ?? 0) !== 1) {
                return false; // البروفايل غير مكتمل
            }

            // 2. جلب دعوة pending
            $referral = $db->table('rewards_referrals')
                ->where('referred_user_id', $referredUserId)
                ->where('status', 'pending')
                ->first();

            if (!$referral) {
                return false; // لا توجد دعوة
            }

            // 3. التحقق من عدم الاحتيال
            $referrerUserId = (int)$referral->referrer_user_id;
            if ($referrerUserId === $referredUserId) {
                \Log::warning("RewardsHelper: User {$referredUserId} tried to reward themselves");
                return false;
            }

            $now = Carbon::now('Asia/Amman');

            // 4. بدء معاملة قاعدة البيانات
            $db->beginTransaction();

            try {
                // 5. تحديث حالة الدعوة
                $db->table('rewards_referrals')
                    ->where('id', $referral->id)
                    ->update([
                        'status'       => 'completed',
                        'completed_at' => $now,
                    ]);

                $db->table('rewards_referrals')
                    ->where('id', $referral->id)
                    ->update([
                        'status'      => 'rewarded',
                        'rewarded_at' => $now,
                    ]);

                // 6. جلب أو إنشاء سجل نقاط للمدعو
                $rewardsUser = $db->table('rewards_users')
                    ->where('user_id', $referrerUserId)
                    ->first();

                if (!$rewardsUser) {
                    $db->table('rewards_users')->insert([
                        'user_id'      => $referrerUserId,
                        'total_points' => 0,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }

                // 7. إضافة 100 نقطة
                $db->table('rewards_users')
                    ->where('user_id', $referrerUserId)
                    ->increment('total_points', 100);

                // 8. إضافة معاملة
                $db->table('rewards_transactions')->insert([
                    'user_id'        => $referrerUserId,
                    'points'          => 100,
                    'reward_type'    => 'friend_invite',
                    'reward_type_id' => $referral->id,
                    'description'    => "دعوة صديق: المستخدم #{$referredUserId}",
                    'created_at'     => $now,
                ]);

                $db->commit();
                return true;
            } catch (\Throwable $e) {
                $db->rollBack();
                \Log::error('RewardsHelper::checkAndRewardReferral transaction error: ' . $e->getMessage());
                return false;
            }
        } catch (\Throwable $e) {
            \Log::error('RewardsHelper::checkAndRewardReferral error: ' . $e->getMessage());
            return false;
        }
    }
}

