<?php

declare(strict_types=1);

namespace App\Services\AiTools;

use App\Models\AiJobModel;
use App\Models\AiJobTypeModel;
use App\Services\RewardsService;
use App\Services\SubscriptionService;
use App\Helpers\StudentViewHelper;
use Config\Database;

/**
 * AiToolAccessService: خدمة إدارة الوصول لأدوات الذكاء الاصطناعي
 * تتحقق من شروط النقاط والاشتراك وتدير خصم/إرجاع النقاط
 */
class AiToolAccessService
{
    private RewardsService $rewardsService;
    private SubscriptionService $subscriptionService;
    private AiJobTypeModel $jobTypeModel;
    private AiJobModel $jobModel;

    public function __construct()
    {
        $this->rewardsService = new RewardsService();
        $this->subscriptionService = new SubscriptionService(Database::connect());
        $this->jobTypeModel = new AiJobTypeModel();
        $this->jobModel = new AiJobModel();
    }

    /**
     * التحقق من كون الحساب حساب اختبار (من .env)
     */
    public function isTestAccount(int $userId): bool
    {
        $testUserIds = getenv('AI_TOOLS_TEST_USER_IDS');
        if (empty($testUserIds)) {
            return false;
        }

        $testIds = array_map('trim', explode(',', $testUserIds));
        $testIds = array_filter($testIds, fn($id) => is_numeric($id));

        return in_array((string)$userId, $testIds, true);
    }

    /**
     * التحقق من إمكانية الوصول للأداة
     *
     * @param int $userId
     * @param string $typeKey
     * @param int $taskId
     * @param string|null $studentId
     * @return array ['can_access' => bool, 'reason' => string, 'points_cost' => int, 'user_points' => int, 'has_active_plan' => bool, 'has_free_usage' => bool, 'message' => string]
     */
    public function checkAccess(int $userId, string $typeKey, int $taskId, ?string $studentId = null): array
    {
        // 1. التحقق من حساب الاختبار أولاً
        if ($this->isTestAccount($userId)) {
            return [
                'can_access' => true,
                'reason' => 'test_account',
                'points_cost' => 0,
                'user_points' => 0,
                'has_active_plan' => false,
                'has_free_usage' => false,
                'message' => 'حساب اختبار - الوصول مسموح',
                'is_test_account' => true,
            ];
        }

        // 1.5. التحقق من أن الآدمن في وضع الاستعراض - استخدام مجاني دائماً
        if (StudentViewHelper::isAdminViewingAsStudent()) {
            return [
                'can_access' => true,
                'reason' => 'admin_preview',
                'points_cost' => 0,
                'user_points' => 0,
                'has_active_plan' => false,
                'has_free_usage' => false,
                'message' => 'وضع استعراض الآدمن - الوصول مجاني',
                'is_test_account' => false,
            ];
        }

        // 2. جلب معلومات الأداة
        $jobType = $this->jobTypeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return [
                'can_access' => false,
                'reason' => 'tool_not_found',
                'points_cost' => 0,
                'user_points' => 0,
                'has_active_plan' => false,
                'has_free_usage' => false,
                'message' => 'الأداة غير موجودة أو معطلة',
                'is_test_account' => false,
            ];
        }

        $pointsCost = (int)($jobType['points_cost'] ?? 30);
        $jobTypeId = (int)$jobType['id'];

        // 3. جلب معلومات المستخدم
        $userPoints = $this->rewardsService->getUserPoints($userId);
        $activePlan = $this->subscriptionService->getActivePlanForUser($userId);
        $hasActivePlan = $activePlan !== null;

        // 4. التحقق من الاستخدام المجاني السابق
        $hasFreeUsage = $this->jobModel->hasFreeUsageInTask($userId, $taskId, $jobTypeId, $studentId);

        // 5. منطق التحقق
        // إذا كان هناك خطة فعالة ولا يوجد استخدام مجاني سابق
        if ($hasActivePlan && !$hasFreeUsage) {
            return [
                'can_access' => true,
                'reason' => 'free_usage',
                'points_cost' => $pointsCost,
                'user_points' => $userPoints,
                'has_active_plan' => true,
                'has_free_usage' => false,
                'message' => 'يمكنك استخدام الأداة مجاناً',
                'is_test_account' => false,
            ];
        }

        // إذا كان هناك خطة فعالة ولكن يوجد استخدام مجاني سابق
        if ($hasActivePlan && $hasFreeUsage) {
            if ($userPoints >= $pointsCost) {
                return [
                    'can_access' => true,
                    'reason' => 'points',
                    'points_cost' => $pointsCost,
                    'user_points' => $userPoints,
                    'has_active_plan' => true,
                    'has_free_usage' => true,
                    'message' => "سيتم خصم {$pointsCost} نقطة",
                    'is_test_account' => false,
                ];
            } else {
                return [
                    'can_access' => false,
                    'reason' => 'insufficient_points',
                    'points_cost' => $pointsCost,
                    'user_points' => $userPoints,
                    'has_active_plan' => true,
                    'has_free_usage' => true,
                    'message' => "نقاطك غير كافية. تحتاج {$pointsCost} نقطة ولكن لديك {$userPoints} نقطة فقط. يرجى شراء نقاط إضافية أو ترقية اشتراكك.",
                    'is_test_account' => false,
                ];
            }
        }

        // إذا لم تكن هناك خطة فعالة
        if (!$hasActivePlan) {
            if ($userPoints >= $pointsCost) {
                return [
                    'can_access' => true,
                    'reason' => 'points',
                    'points_cost' => $pointsCost,
                    'user_points' => $userPoints,
                    'has_active_plan' => false,
                    'has_free_usage' => false,
                    'message' => "سيتم خصم {$pointsCost} نقطة",
                    'is_test_account' => false,
                ];
            } else {
                return [
                    'can_access' => false,
                    'reason' => 'no_subscription_and_insufficient_points',
                    'points_cost' => $pointsCost,
                    'user_points' => $userPoints,
                    'has_active_plan' => false,
                    'has_free_usage' => false,
                    'message' => "لا تملك اشتراكاً فعالاً ولا تملك نقاط كافية. تحتاج {$pointsCost} نقطة ولكن لديك {$userPoints} نقطة فقط. يرجى شراء نقاط إضافية أو الاشتراك في إحدى خططنا.",
                    'is_test_account' => false,
                ];
            }
        }

        // الحالة الافتراضية (لا يجب الوصول إليها)
        return [
            'can_access' => false,
            'reason' => 'unknown',
            'points_cost' => $pointsCost,
            'user_points' => $userPoints,
            'has_active_plan' => $hasActivePlan,
            'has_free_usage' => $hasFreeUsage,
            'message' => 'حدث خطأ في التحقق من الوصول',
            'is_test_account' => false,
        ];
    }

    /**
     * خصم النقاط عند الحاجة
     * يستخدم Database Transactions و row-level locking لمنع Race Conditions
     *
     * @param int $userId
     * @param string $typeKey
     * @param int $taskId
     * @param string|null $studentId
     * @return array ['success' => bool, 'points_deducted' => int, 'points_cost' => int, 'remaining_points' => int, 'message' => string]
     */
    public function deductPointsIfNeeded(int $userId, string $typeKey, int $taskId, ?string $studentId = null): array
    {
        // التحقق من حساب الاختبار
        if ($this->isTestAccount($userId)) {
            return [
                'success' => true,
                'points_deducted' => 0,
                'points_cost' => 0,
                'remaining_points' => 0,
                'message' => 'حساب اختبار - لم يتم خصم نقاط',
                'is_test_account' => true,
            ];
        }

        // التحقق من الوصول
        $accessCheck = $this->checkAccess($userId, $typeKey, $taskId, $studentId);
        
        if (!$accessCheck['can_access']) {
            return [
                'success' => false,
                'points_deducted' => 0,
                'points_cost' => $accessCheck['points_cost'],
                'remaining_points' => $accessCheck['user_points'],
                'message' => $accessCheck['message'],
                'is_test_account' => false,
            ];
        }

        // إذا كان استخدام مجاني أو استعراض الآدمن، لا يتم خصم نقاط
        if ($accessCheck['reason'] === 'free_usage' || $accessCheck['reason'] === 'admin_preview') {
            return [
                'success' => true,
                'points_deducted' => 0,
                'points_cost' => 0,
                'remaining_points' => $accessCheck['user_points'],
                'message' => $accessCheck['reason'] === 'admin_preview' 
                    ? 'وضع استعراض الآدمن - لم يتم خصم نقاط'
                    : 'استخدام مجاني - لم يتم خصم نقاط',
                'is_test_account' => false,
            ];
        }

        // خصم النقاط مع Transaction و row-level locking
        $pointsCost = $accessCheck['points_cost'];
        $db = Database::connect();

        // وضع api: قرار الصرف من Plans (المحفظة المركزية هي الحَكَم، fail-closed).
        // في off/shadow يبقى السلوك المحلي كما هو حرفيًا.
        $plansClient = null;
        $plansApi    = false;
        try {
            $plansClient = new \App\Libraries\PlansClient();
            $plansApi    = ($plansClient->mode() === 'api');
        } catch (\Throwable $e) {
            $plansApi = false; // تعذّر تهيئة العميل → عامل المسار كمحلي
        }

        $plansCharged = false;
        $plansKey     = '';

        try {
            $db->transException(true)->transStart();

            // 🔒 قفل صف النقاط للمستخدم لمنع Race Conditions
            $userPointsRow = $db->query(
                'SELECT total_points FROM rewards_users WHERE user_id = ? FOR UPDATE',
                [$userId]
            )->getRowArray();

            // إنشاء السجل إذا لم يكن موجوداً
            if (!$userPointsRow) {
                $db->table('rewards_users')->insert([
                    'user_id' => $userId,
                    'total_points' => 0,
                ]);
                $userPointsRow = ['total_points' => 0];
            }

            $currentPoints = (int)($userPointsRow['total_points'] ?? 0);

            // التحقق المحلي من الكفاية — في وضع api القرار لـ Plans (الرصيد المحلي مجرد كاش قد يتأخر)
            if (! $plansApi && $currentPoints < $pointsCost) {
                $db->transRollback();
                return [
                    'success' => false,
                    'points_deducted' => 0,
                    'points_cost' => $pointsCost,
                    'remaining_points' => $currentPoints,
                    'message' => "نقاطك غير كافية. تحتاج {$pointsCost} نقطة ولكن لديك {$currentPoints} فقط",
                    'is_test_account' => false,
                ];
            }

            // خصم النقاط مباشرة في نفس Transaction
            $newTotal = $currentPoints - $pointsCost;
            $db->table('rewards_users')
                ->where('user_id', $userId)
                ->update(['total_points' => $newTotal]);

            // تسجيل المعاملة في نفس Transaction
            $db->table('rewards_transactions')->insert([
                'user_id'       => $userId,
                'points'        => -$pointsCost, // سالب للخصم
                'reward_type'   => 'ai_tool_usage',
                'reward_type_id' => $taskId,
                'description'   => "استخدام أداة الذكاء الاصطناعي: {$typeKey}",
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            $txnId = (int) $db->insertID(); // معرف معاملة الخصم = مفتاح idempotency لـ Plans

            // وضع api: اصرف من Plans قبل الالتزام المحلي — رفضُ Plans يلغي كل شيء.
            if ($plansApi) {
                $plansKey = 'diploma:spend:txn:' . $txnId;
                $res = $plansClient->spend($userId, $pointsCost, $plansKey, [
                    'event_type'     => 'diploma.aitool.spend',
                    'event_ref_type' => 'rewards_txn',
                    'event_ref_id'   => (string) $txnId,
                    'description'    => 'استخدام أداة: ' . $typeKey . ' (مهمة ' . $taskId . ')',
                ]);

                if (! ($res['ok'] ?? false)) {
                    $db->transRollback();
                    $plansBal = (int) ($res['balance'] ?? ($res['body']['balance'] ?? 0));
                    if ((int) ($res['status'] ?? 0) === 402) {
                        return [
                            'success' => false,
                            'points_deducted' => 0,
                            'points_cost' => $pointsCost,
                            'remaining_points' => $plansBal,
                            'message' => "نقاطك غير كافية. تحتاج {$pointsCost} نقطة ولكن لديك {$plansBal} فقط",
                            'is_test_account' => false,
                        ];
                    }
                    log_message('error', '[PlansSpend] user=' . $userId . ' cost=' . $pointsCost . ' key=' . $plansKey . ' failed: ' . json_encode($res));
                    return [
                        'success' => false,
                        'points_deducted' => 0,
                        'points_cost' => $pointsCost,
                        'remaining_points' => $accessCheck['user_points'],
                        'message' => 'خدمة النقاط غير متاحة مؤقتًا، حاول بعد قليل',
                        'is_test_account' => false,
                    ];
                }

                $plansCharged = true;

                // زامِن الكاش المحلي مع رصيد Plans بعد الخصم (يعالج أي انحراف ذاتيًا)
                if (isset($res['balance']) && $res['balance'] !== null) {
                    $newTotal = (int) $res['balance'];
                    $db->table('rewards_users')
                        ->where('user_id', $userId)
                        ->update(['total_points' => $newTotal]);
                }
            }

            $db->transComplete();

            if ($db->transStatus()) {
                if (! $plansApi) {
                    // ظل المحفظة المركزية (لا يؤثر على النتيجة المحلّية إطلاقًا)
                    $this->mirrorSpendShadow($userId, $pointsCost, $txnId, $typeKey, $taskId, $newTotal);
                }
                return [
                    'success' => true,
                    'points_deducted' => $pointsCost,
                    'points_cost' => $pointsCost,
                    'remaining_points' => $newTotal,
                    'message' => "تم خصم {$pointsCost} نقطة بنجاح",
                    'is_test_account' => false,
                ];
            } else {
                // نجح خصم Plans لكن فشل الالتزام المحلي → أعد النقاط مركزيًا (تعويض)
                $this->compensatePlansSpend($plansCharged, $plansKey, $userId, $pointsCost);
                return [
                    'success' => false,
                    'points_deducted' => 0,
                    'points_cost' => $pointsCost,
                    'remaining_points' => $currentPoints,
                    'message' => 'فشل خصم النقاط - خطأ في المعاملة',
                    'is_test_account' => false,
                ];
            }
        } catch (\Throwable $e) {
            if ($db->transStatus() !== false) {
                $db->transRollback();
            }
            $this->compensatePlansSpend($plansCharged, $plansKey, $userId, $pointsCost);
            log_message('error', 'AiToolAccessService::deductPointsIfNeeded error: ' . $e->getMessage());
            return [
                'success' => false,
                'points_deducted' => 0,
                'points_cost' => $pointsCost,
                'remaining_points' => $accessCheck['user_points'],
                'message' => 'فشل خصم النقاط - خطأ في النظام',
                'is_test_account' => false,
            ];
        }
    }

    /**
     * تعويض مركزي: خُصم من Plans لكن فشل الالتزام المحلي بعده → أعد النقاط.
     * idempotent (مفتاح مشتق من مفتاح الخصم) وآمن تمامًا للاستدعاء غير الضروري.
     */
    private function compensatePlansSpend(bool $plansCharged, string $plansKey, int $userId, int $pointsCost): void
    {
        if (! $plansCharged || $plansKey === '') {
            return;
        }
        try {
            $client = new \App\Libraries\PlansClient();
            $res = $client->refund($userId, $pointsCost, $plansKey . ':compensate', [
                'event_type'     => 'diploma.aitool.spend.compensate',
                'event_ref_type' => 'plans_key',
                'event_ref_id'   => $plansKey,
                'description'    => 'تعويض: فشل الالتزام المحلي بعد خصم مركزي ناجح',
            ]);
            log_message('error', '[PlansSpend][compensate] user=' . $userId . ' key=' . $plansKey . ' ok=' . (($res['ok'] ?? false) ? '1' : '0'));
        } catch (\Throwable $e) {
            log_message('critical', '[PlansSpend][compensate] FAILED user=' . $userId . ' key=' . $plansKey . ' err=' . $e->getMessage());
        }
    }

    /**
     * ظل المحفظة المركزية لعملية صرف ناجحة محليًا.
     * في وضع shadow: يستدعي Plans ويسجّل المقارنة فقط، ويعمل دائمًا على النتيجة المحلّية.
     * مغلَّف بالكامل: أي خطأ هنا لا يؤثر على مسار الصرف الحيّ.
     */
    private function mirrorSpendShadow(int $userId, int $pointsCost, int $txnId, string $typeKey, int $taskId, int $localRemaining): void
    {
        try {
            $client = new \App\Libraries\PlansClient();
            if (! $client->enabled() || $txnId <= 0) {
                return;
            }
            $key = 'diploma:spend:txn:' . $txnId;
            $res = $client->spend($userId, $pointsCost, $key, [
                'event_type'     => 'diploma.aitool.spend',
                'event_ref_type' => 'rewards_txn',
                'event_ref_id'   => (string) $txnId,
                'description'    => 'shadow: ' . $typeKey . ' task ' . $taskId,
            ]);
            log_message('info', sprintf(
                '[PlansShadow][spend] user=%d cost=%d local_remaining=%d plans_ok=%s status=%s plans_balance=%s key=%s',
                $userId, $pointsCost, $localRemaining,
                ($res['ok'] ?? false) ? '1' : '0',
                (string) ($res['status'] ?? ''), (string) ($res['balance'] ?? ''), $key
            ));
        } catch (\Throwable $e) {
            log_message('warning', 'PlansShadow spend hook error: ' . $e->getMessage());
        }
    }

    /**
     * إرجاع النقاط عند فشل المهمة
     * يتحقق من أن النقاط تم خصمها فعلياً قبل الإرجاع
     *
     * @param int $jobId
     * @return bool
     */
    public function refundPoints(int $jobId): bool
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return false;
        }

        // التحقق من أن النقاط تم خصمها
        $pointsDeducted = (int)($job['points_deducted'] ?? 0);
        if ($pointsDeducted !== 1) {
            return true; // لم يتم خصم نقاط، لا حاجة لإرجاع
        }

        $pointsCost = (int)($job['points_cost'] ?? 0);
        if ($pointsCost <= 0) {
            return true; // لا توجد نقاط للإرجاع
        }

        $userId = (int)$job['user_id'];
        
        // التحقق من حساب الاختبار
        if ($this->isTestAccount($userId)) {
            return true; // حساب اختبار، لا حاجة لإرجاع
        }

        // التحقق من وجود معاملة خصم سابقة في rewards_transactions
        $db = Database::connect();
        $existingRefund = $db->table('rewards_transactions')
            ->where('user_id', $userId)
            ->where('reward_type', 'ai_tool_refund')
            ->where('reward_type_id', $jobId)
            ->where('points', $pointsCost)
            ->get()
            ->getRowArray();

        if ($existingRefund) {
            log_message('warning', "AiToolAccessService::refundPoints - Points already refunded for job {$jobId}");
            return true; // تم إرجاع النقاط مسبقاً
        }

        // التحقق من وجود معاملة خصم سابقة
        $deductionTransaction = $db->table('rewards_transactions')
            ->where('user_id', $userId)
            ->where('reward_type', 'ai_tool_usage')
            ->where('reward_type_id', $job['task_id'] ?? null)
            ->where('points', -$pointsCost) // سالب للخصم
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getRowArray();

        if (!$deductionTransaction) {
            log_message('warning', "AiToolAccessService::refundPoints - No deduction transaction found for job {$jobId}");
            return false; // لم يتم العثور على معاملة خصم، لا يمكن إرجاع النقاط
        }

        // إرجاع النقاط
        $success = $this->rewardsService->addPoints(
            $userId,
            $pointsCost, // موجب للإرجاع
            'ai_tool_refund',
            $jobId,
            "إرجاع نقاط لاستخدام أداة فاشل (Job ID: {$jobId})"
        );

        return $success;
    }
}

