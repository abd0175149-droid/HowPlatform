<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RewardsUserModel;
use App\Models\RewardsTransactionModel;
use App\Models\RewardsReferralModel;
use App\Models\RewardsConsecutiveDaysModel;
use App\Models\RewardsDailyLoginModel;
use App\Models\DiplomaUserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * RewardsService
 * خدمة رئيسية لإدارة نظام المكافآت
 */
class RewardsService
{
    protected RewardsUserModel $rewardsUserModel;
    protected RewardsTransactionModel $transactionModel;
    protected RewardsReferralModel $referralModel;
    protected RewardsConsecutiveDaysModel $consecutiveDaysModel;
    protected RewardsDailyLoginModel $dailyLoginModel;
    protected DiplomaUserModel $diplomaUserModel;

    public function __construct()
    {
        $this->rewardsUserModel      = model(RewardsUserModel::class);
        $this->transactionModel      = model(RewardsTransactionModel::class);
        $this->referralModel         = model(RewardsReferralModel::class);
        $this->consecutiveDaysModel  = model(RewardsConsecutiveDaysModel::class);
        $this->dailyLoginModel       = model(RewardsDailyLoginModel::class);
        $this->diplomaUserModel      = model(DiplomaUserModel::class);
    }

    /**
     * إضافة نقاط للمستخدم
     *
     * @param int $userId معرف المستخدم
     * @param int $points عدد النقاط (يمكن أن تكون سالبة)
     * @param string $type نوع المكافأة (friend_invite, consecutive_days, etc.)
     * @param int|null $typeId معرف العملية المرتبطة
     * @param string|null $description وصف العملية
     * @param string|null $createdAt وقت العملية (مثلاً Asia/Amman) Y-m-d H:i:s؛ إن غاب يُستخدم توقيت السيرفر
     * @return bool
     */
    public function addPoints(
        int $userId,
        int $points,
        string $type,
        ?int $typeId = null,
        ?string $description = null,
        ?string $createdAt = null
    ): bool {
        $db = db_connect();

        try {
            $db->transException(true)->transStart();

            $this->rewardsUserModel->addPoints($userId, $points);
            $this->transactionModel->addTransaction($userId, $points, $type, $typeId, $description, $createdAt);

            $db->transComplete();

            return $db->transStatus();
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'RewardsService::addPoints DB error: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'RewardsService::addPoints error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب نقاط المستخدم
     *
     * @param int $userId
     * @return int
     */
    public function getUserPoints(int $userId): int
    {
        try {
            $user = $this->rewardsUserModel->getOrCreate($userId);
            $points = (int)($user['total_points'] ?? 0);
            log_message('debug', "[RewardsService::getUserPoints] userId: {$userId}, points: {$points}, user_data: " . json_encode($user));
            return $points;
        } catch (\Throwable $e) {
            log_message('error', "[RewardsService::getUserPoints] Error for userId: {$userId} - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * جلب سجل المعاملات
     *
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getUserTransactions(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->transactionModel->getUserTransactions($userId, $limit, $offset);
    }
    
    /**
     * جلب عدد المعاملات الإجمالي للمستخدم
     *
     * @param int $userId
     * @return int
     */
    public function getUserTransactionsCount(int $userId): int
    {
        return $this->transactionModel->getUserTransactionsCount($userId);
    }

    /**
     * التحقق من إكمال البروفايل ومنح مكافأة الدعوة
     *
     * @param int $referredUserId معرف المستخدم المدعو
     * @return bool
     */
    public function checkAndRewardReferral(int $referredUserId): bool
    {
        // 1. التحقق من إكمال البروفايل
        $diplomaUser = $this->diplomaUserModel->where('user_id', $referredUserId)->first();
        if (!$diplomaUser || (int)($diplomaUser['is_complete'] ?? 0) !== 1) {
            return false; // البروفايل غير مكتمل
        }

        // 2. جلب دعوة pending
        $referral = $this->referralModel->getReferralByReferredUser($referredUserId);
        if (!$referral) {
            return false; // لا توجد دعوة
        }

        // 3. التحقق من عدم منح المكافأة مسبقاً
        if ($referral['status'] === 'rewarded') {
            return false; // تم منح المكافأة مسبقاً
        }

        // 4. التحقق من عدم الاحتيال (المستخدم لا يدعو نفسه)
        $referrerUserId = (int)$referral['referrer_user_id'];
        if ($referrerUserId === $referredUserId) {
            log_message('warning', "RewardsService: User {$referredUserId} tried to refer themselves");
            return false;
        }

        // 5. جلب اسم المستخدم المدعو من قاعدة auth
        $referredUserName = 'مستخدم #' . $referredUserId;
        try {
            $authDb = \Config\Database::connect('remote');
            $authUser = $authDb->query("
                SELECT name 
                FROM users 
                WHERE id = ?
            ", [$referredUserId])->getRowArray();
            
            if ($authUser && !empty($authUser['name'])) {
                $referredUserName = $authUser['name'];
            }
        } catch (\Exception $e) {
            log_message('debug', 'Could not fetch user name for ID: ' . $referredUserId . ' - ' . $e->getMessage());
        }

        $db = db_connect();

        try {
            $db->transException(true)->transStart();

            // 6. تحديث حالة الدعوة
            $this->referralModel->markAsCompleted((int)$referral['id']);
            $this->referralModel->markAsRewarded((int)$referral['id']);

            // 7. منح 100 نقطة للمدعو (referrer_user_id)
            $this->addPoints(
                $referrerUserId,
                100,
                'friend_invite',
                (int)$referral['id'],
                "دعوة صديق: {$referredUserName}"
            );

            $db->transComplete();

            return $db->transStatus();
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'RewardsService::checkAndRewardReferral DB error: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'RewardsService::checkAndRewardReferral error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث streak الأيام المتتالية ومنح المكافأة عند الوصول لـ 5 أيام (ثم إعادة تعيين السلسلة)
     * قاعدة: 5 أيام ثم سلسلة جديدة. انقطاع يوم → سلسلة جديدة من اليوم 1.
     *
     * @param int $userId
     * @return array{streak: int, rewarded: bool}
     */
    public function updateConsecutiveDays(int $userId): array
    {
        try {
            $tz = new \DateTimeZone('Asia/Amman');
            $now = new \DateTime('now', $tz);
            $today = $now->format('Y-m-d');
            $nowAmman = $now->format('Y-m-d H:i:s');

            $record = $this->consecutiveDaysModel->getOrCreate($userId);
            $lastLoginDate = $record['last_login_date'] ?? null;
            $currentStreak = (int)($record['current_streak'] ?? 0);

            $loginRecorded = $this->dailyLoginModel->recordLogin($userId, $today, $nowAmman);
            if (!$loginRecorded) {
                log_message('warning', "RewardsService: Failed to record daily login for user {$userId} on {$today}");
            }

            if ($lastLoginDate === $today) {
                return ['streak' => $currentStreak, 'rewarded' => false];
            }

            $newStreak = 1;
            if ($lastLoginDate) {
                $lastDate = new \DateTime($lastLoginDate, $tz);
                $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
                if ($lastDate->format('Y-m-d') === $yesterday) {
                    if ($currentStreak + 1 > 5) {
                        $newStreak = 1;
                    } else {
                        $newStreak = $currentStreak + 1;
                    }
                }
            }

            $this->consecutiveDaysModel->updateStreak($userId, $newStreak, $today);

            $rewarded = false;
            if ($newStreak === 5) {
                $db = db_connect();
                try {
                    $db->transException(true)->transStart();
                    $this->addPoints(
                        $userId,
                        50,
                        'consecutive_days',
                        null,
                        'استخدام المنصة لـ 5 أيام متتالية',
                        $nowAmman
                    );
                    $this->consecutiveDaysModel->updateStreak($userId, 0, $today);
                    $this->consecutiveDaysModel->updateLastRewardedStreak($userId, 0);
                    $db->transComplete();
                    $rewarded = $db->transStatus();
                } catch (\Throwable $e) {
                    if ($db->transStatus() === false) {
                        $db->transRollback();
                    }
                    log_message('debug', 'RewardsService::updateConsecutiveDays reward error: ' . $e->getMessage());
                }
            }

            $streakToReturn = $rewarded ? 0 : $newStreak;
            return ['streak' => $streakToReturn, 'rewarded' => $rewarded];
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsService::updateConsecutiveDays error: ' . $e->getMessage());
            return ['streak' => 0, 'rewarded' => false];
        }
    }

    /**
     * جلب إحصائيات المستخدم
     *
     * @param int $userId
     * @return array
     */
    public function getUserStats(int $userId): array
    {
        $points = $this->getUserPoints($userId);
        $record = $this->consecutiveDaysModel->getOrCreate($userId);
        $referrals = $this->referralModel->getUserReferrals($userId);

        $completedReferrals = array_filter($referrals, fn($r) => in_array($r['status'], ['completed', 'rewarded']));
        $pendingReferrals = array_filter($referrals, fn($r) => $r['status'] === 'pending');

        return [
            'total_points'         => $points,
            'current_streak'       => (int)($record['current_streak'] ?? 0),
            'total_referrals'      => count($referrals),
            'completed_referrals'  => count($completedReferrals),
            'pending_referrals'    => count($pendingReferrals),
        ];
    }
}

