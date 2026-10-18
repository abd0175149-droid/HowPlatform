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

        // وضع api: القيد الحاكم في Plans (داخل المعاملة، الرفض يلغي الكتابة المحلية).
        $plansClient = null;
        $plansApi    = false;
        try {
            $plansClient = new \App\Libraries\PlansClient();
            $plansApi    = ($plansClient->mode() === 'api');
        } catch (\Throwable $e) {
            $plansApi = false;
        }

        $plansApplied = false;
        $plansKind    = '';
        $plansKey     = '';

        try {
            $db->transException(true)->transStart();

            $this->rewardsUserModel->addPoints($userId, $points);
            $this->transactionModel->addTransaction($userId, $points, $type, $typeId, $description, $createdAt);

            // التقط معرّف القيد قبل transComplete: الـ COMMIT يُصفّر insertID() إلى 0.
            $txnId = (int) $db->insertID();

            if ($plansApi && $txnId > 0) {
                $plansKind = ($type === 'ai_tool_refund') ? 'refund' : ($points >= 0 ? 'earn' : 'spend');
                // مفتاح مستقر لاسترجاع الأدوات (واحد لكل مهمة) — يمنع تكرار القيد عند إعادة المحاولة.
                $plansKey = ($type === 'ai_tool_refund' && $typeId !== null)
                    ? 'diploma:refund:job:' . $typeId
                    : 'diploma:' . $plansKind . ':txn:' . $txnId;
                $res = $plansClient->{$plansKind}($userId, $points, $plansKey, [
                    'event_type'     => 'diploma.' . $type,
                    'event_ref_type' => 'rewards_txn',
                    'event_ref_id'   => (string) $txnId,
                    'description'    => $description ?: ($type . ($typeId !== null ? (' #' . $typeId) : '')),
                ]);

                if (! ($res['ok'] ?? false)) {
                    $db->transRollback();
                    log_message('error', '[PlansWallet][' . $plansKind . '] user=' . $userId . ' points=' . $points . ' key=' . $plansKey . ' failed: ' . json_encode($res));
                    return false;
                }

                $plansApplied = true;

                // زامِن الكاش المحلي مع رصيد Plans (يعالج أي انحراف ذاتيًا)
                if (isset($res['balance']) && $res['balance'] !== null) {
                    $db->table('rewards_users')
                        ->where('user_id', $userId)
                        ->update(['total_points' => (int) $res['balance']]);
                }
            }

            $db->transComplete();

            $ok = $db->transStatus();
            if (! $ok && $plansApplied) {
                // طُبّق في Plans لكن فشل الالتزام المحلي → اعكس القيد المركزي (تعويض idempotent)
                $this->compensatePlansWallet($plansClient, $plansKind, $plansKey, $userId, $points);
            }
            if ($ok && $txnId > 0 && ! $plansApi) {
                // ظل المحفظة المركزية (لا يؤثر على النتيجة المحلّية إطلاقًا).
                $this->mirrorWalletShadow($userId, $points, $type, $typeId, $txnId);
            }
            return $ok;
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            if ($plansApplied) {
                $this->compensatePlansWallet($plansClient, $plansKind, $plansKey, $userId, $points);
            }
            log_message('error', 'RewardsService::addPoints DB error: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            if ($plansApplied) {
                $this->compensatePlansWallet($plansClient, $plansKind, $plansKey, $userId, $points);
            }
            log_message('error', 'RewardsService::addPoints error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تعويض مركزي: طُبّق قيد في Plans لكن فشل الالتزام المحلي بعده → اعكسه.
     * earn/refund يُعكَس بـ spend، وspend يُعكَس بـ refund. idempotent عبر مفتاح مشتق.
     */
    private function compensatePlansWallet(?\App\Libraries\PlansClient $client, string $kind, string $key, int $userId, int $points): void
    {
        if ($client === null || $kind === '' || $key === '') {
            return;
        }
        try {
            $reverse = ($kind === 'spend') ? 'refund' : 'spend';
            $res = $client->{$reverse}($userId, $points, $key . ':compensate', [
                'event_type'     => 'diploma.wallet.compensate',
                'event_ref_type' => 'plans_key',
                'event_ref_id'   => $key,
                'description'    => 'تعويض: فشل الالتزام المحلي بعد قيد مركزي ناجح',
            ]);
            log_message('error', '[PlansWallet][compensate] user=' . $userId . ' key=' . $key . ' ok=' . (($res['ok'] ?? false) ? '1' : '0'));
        } catch (\Throwable $e) {
            log_message('critical', '[PlansWallet][compensate] FAILED user=' . $userId . ' key=' . $key . ' err=' . $e->getMessage());
        }
    }

    /**
     * ظل المحفظة المركزية لعملية كسب/استرجاع ناجحة محليًا.
     * يختار نقطة Plans المناسبة: ai_tool_refund=refund، الموجب=earn، السالب=spend.
     * مغلَّف بالكامل: أي خطأ هنا لا يؤثر على النتيجة المحلّية.
     */
    private function mirrorWalletShadow(int $userId, int $points, string $type, ?int $typeId, int $txnId): void
    {
        try {
            $client = new \App\Libraries\PlansClient();
            if (! $client->enabled() || $txnId <= 0) {
                return;
            }
            $kind = ($type === 'ai_tool_refund') ? 'refund' : ($points >= 0 ? 'earn' : 'spend');
            $key  = 'diploma:' . $kind . ':txn:' . $txnId;
            $meta = [
                'event_type'     => 'diploma.' . $type,
                'event_ref_type' => 'rewards_txn',
                'event_ref_id'   => (string) $txnId,
                'description'    => 'shadow: ' . $type . ($typeId !== null ? (' #' . $typeId) : ''),
            ];
            $res = $client->{$kind}($userId, $points, $key, $meta);
            log_message('info', sprintf(
                '[PlansShadow][%s] user=%d points=%d type=%s plans_ok=%s status=%s plans_balance=%s key=%s',
                $kind, $userId, $points, $type,
                ($res['ok'] ?? false) ? '1' : '0',
                (string) ($res['status'] ?? ''), (string) ($res['balance'] ?? ''), $key
            ));
        } catch (\Throwable $e) {
            log_message('warning', 'PlansShadow earn hook error: ' . $e->getMessage());
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
        // وضع api: اقرأ الرصيد من Plans (المصدر الموثوق) مع fail-open للقراءة المحلّية.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->mode() === 'api') {
                $bal = $client->balance($userId);
                if ($bal !== null) return $bal;
            }
        } catch (\Throwable $e) {
            // تابع للقراءة المحلّية
        }

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
        // وضع api: سجل المعاملات من Plans (المصدر الموثوق) مع fail-open للقراءة المحلّية.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->txEnabled()) {
                $res = $client->transactions($userId, $limit, $offset);
                if (is_array($res) && isset($res['transactions']) && is_array($res['transactions'])) {
                    return array_map(static function (array $t): array {
                        return [
                            'reward_type' => (string) ($t['kind'] ?? $t['event_type'] ?? ''),
                            'description' => (string) ($t['description'] ?? ''),
                            'points'      => (int) ($t['points'] ?? 0),
                            'created_at'  => (string) ($t['created_at'] ?? ''),
                        ];
                    }, $res['transactions']);
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'RewardsService::getUserTransactions Plans failed: ' . $e->getMessage());
        }

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
        // وضع api: العدد الإجمالي من Plans (total) مع fail-open للعد المحلّي.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->txEnabled()) {
                $res = $client->transactions($userId, 1, 0);
                if (is_array($res) && isset($res['total'])) {
                    return (int) $res['total'];
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'RewardsService::getUserTransactionsCount Plans failed: ' . $e->getMessage());
        }

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

        // وضع api: Plans يمنح مكافأة الإحالة (يحتاج دعوة مسجّلة في Plans).
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->earningEnabled()) {
                $res = $client->completeReferral($referredUserId);
                return is_array($res) && ($res['ok'] ?? false);
            }
        } catch (\Throwable $e) {
            // تابع للمنطق المحلّي
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
        // وضع api: Plans يحسب التتابع ويمنح المكافأة (لا كتابة محلّية).
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->earningEnabled()) {
                $res = $client->earnLogin($userId);
                if (is_array($res) && ($res['ok'] ?? false)) {
                    return ['streak' => (int) ($res['streak'] ?? 0), 'rewarded' => (bool) ($res['rewarded'] ?? false)];
                }
                // Plans غير متاح → تجاهل هذا التحميل (لا fallback محلّي حتى لا نُعيد الكتابة المحلّية)
                return ['streak' => 0, 'rewarded' => false];
            }
        } catch (\Throwable $e) {
            // تابع للمنطق المحلّي
        }

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
        // وضع api: الإحصائيات من Plans (المصدر الموثوق) مع fail-open للحساب المحلّي.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->statsEnabled()) {
                $res = $client->stats($userId);
                if (is_array($res) && ($res['ok'] ?? false)) {
                    return [
                        'total_points'         => (int) ($res['total_points'] ?? 0),
                        'current_streak'       => (int) ($res['current_streak'] ?? 0),
                        'total_referrals'      => (int) ($res['total_referrals'] ?? 0),
                        'completed_referrals'  => (int) ($res['completed_referrals'] ?? 0),
                        'pending_referrals'    => (int) ($res['pending_referrals'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'RewardsService::getUserStats Plans failed: ' . $e->getMessage());
        }

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

