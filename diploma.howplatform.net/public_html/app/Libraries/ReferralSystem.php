<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\RewardsReferralModel;
use App\Services\RewardsService;

/**
 * ReferralSystem
 * نظام إدارة دعوات الأصدقاء
 */
class ReferralSystem
{
    protected RewardsReferralModel $referralModel;
    protected RewardsService $rewardsService;

    public function __construct()
    {
        $this->referralModel = model(RewardsReferralModel::class);
        $this->rewardsService = new \App\Services\RewardsService();
    }

    /**
     * إنشاء كود دعوة ثابت للمستخدم
     * الكود يكون ثابتاً لكل مستخدم ليمكن استخدامه عدة مرات
     *
     * @param int $userId
     * @return string
     */
    public function generateReferralCode(int $userId): string
    {
        // إنشاء كود ثابت بناءً على user_id فقط
        // هذا يجعل الكود ثابتاً لكل مستخدم ويمكن استخدامه عدة مرات
        $hash = md5('referral_' . $userId . '_howplatform');
        $code = strtoupper(substr($hash, 0, 8));
        
        // إضافة بادئة للتمييز
        return 'REF' . $code;
    }

    /**
     * تسجيل دعوة جديدة
     *
     * @param int $referrerUserId صاحب كود الدعوة
     * @param int $referredUserId المستخدم المدعو
     * @param string $code كود الدعوة
     * @return int|null معرف السجل
     */
    public function registerReferral(int $referrerUserId, int $referredUserId, string $code): ?int
    {
        // التحقق من عدم الاحتيال
        if ($referrerUserId === $referredUserId) {
            log_message('warning', "ReferralSystem: User {$referrerUserId} tried to refer themselves");
            return null;
        }

        $id = $this->referralModel->registerReferral($referrerUserId, $referredUserId, $code);

        // وضع api: سجّل الدعوة في Plans أيضًا (مصدر الكسب الجديد).
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->earningEnabled()) {
                $client->recordReferral($referrerUserId, $referredUserId);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'ReferralSystem: Plans recordReferral failed: ' . $e->getMessage());
        }

        return $id;
    }

    /**
     * التحقق من إكمال البروفايل ومنح المكافأة
     *
     * @param int $referredUserId
     * @return bool
     */
    public function checkAndRewardReferral(int $referredUserId): bool
    {
        return $this->rewardsService->checkAndRewardReferral($referredUserId);
    }

    /**
     * التحقق من صحة كود الدعوة
     *
     * @param string $code
     * @return int|null معرف صاحب الكود
     */
    public function validateReferralCode(string $code): ?int
    {
        return $this->referralModel->validateReferralCode($code);
    }

    /**
     * جلب كود الدعوة للمستخدم (إن كان موجوداً) أو إنشاء واحد جديد
     *
     * @param int $userId
     * @return string
     */
    public function getOrCreateReferralCode(int $userId): string
    {
        // البحث عن كود دعوة موجود في أي سجل
        $db = \Config\Database::connect();
        $referral = $db->table('rewards_referrals')
            ->where('referrer_user_id', $userId)
            ->orderBy('created_at', 'ASC')
            ->get()
            ->getRowArray();
        
        if ($referral && !empty($referral['referral_code'])) {
            // استخدام الكود الموجود
            return $referral['referral_code'];
        }

        // إنشاء كود جديد (ثابت للمستخدم)
        $code = $this->generateReferralCode($userId);
        
        // لا نحفظ الكود في سجل حتى يتم استخدامه فعلياً
        // الكود ثابت لكل مستخدم، لذلك يمكن التحقق منه مباشرة عند الحاجة
        return $code;
    }

    /**
     * جلب إحصائيات الدعوات للمستخدم
     *
     * @param int $userId
     * @return array
     */
    public function getUserReferralStats(int $userId): array
    {
        // وضع api: عدّادات الإحالات من Plans مع fail-open للحساب المحلّي.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->statsEnabled()) {
                $res = $client->stats($userId);
                if (is_array($res) && ($res['ok'] ?? false)) {
                    return [
                        'total'     => (int) ($res['total_referrals'] ?? 0),
                        'pending'   => (int) ($res['pending_referrals'] ?? 0),
                        'completed' => (int) ($res['completed_referrals'] ?? 0),
                        'rewarded'  => (int) ($res['completed_referrals'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'ReferralSystem::getUserReferralStats Plans failed: ' . $e->getMessage());
        }

        $referrals = $this->referralModel->getUserReferrals($userId);
        
        $stats = [
            'total'     => count($referrals),
            'pending'   => 0,
            'completed' => 0,
            'rewarded'  => 0,
        ];

        foreach ($referrals as $referral) {
            $status = $referral['status'] ?? 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }
}

