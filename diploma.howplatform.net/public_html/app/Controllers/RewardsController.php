<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\FeatureGate;
use App\Libraries\ReferralSystem;
use App\Services\RewardsService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RewardsController
 * واجهة المستخدم لنظام المكافآت
 */
class RewardsController extends BaseController
{
    protected RewardsService $rewardsService;
    protected ReferralSystem $referralSystem;
    protected FeatureGate $featureGate;

    public function __construct()
    {
        $this->rewardsService = new RewardsService();
        $this->referralSystem = new ReferralSystem();
        $this->featureGate = new FeatureGate();
    }

    /**
     * عرض لوحة المكافآت الرئيسية
     * GET /rewards
     */
    public function index(): string
    {
        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');

        // التحقق من تفعيل الميزة
        if (!$this->featureGate->isEnabled('rewards_system', $userId, $userEmail)) {
            return view('rewards/unavailable', [
                'title'   => 'الميزة تحت التطوير',
                'message' => 'نظام المكافآت متاح حاليًا لمجموعة محدودة من المستخدمين. تابعنا قريبًا!',
            ]);
        }

        if ($userId <= 0) {
            return redirect()->to('https://auth.howplatform.net/login?redirect=' . urlencode(current_url()));
        }

        // جلب الإحصائيات
        $stats = $this->rewardsService->getUserStats($userId);
        $points = $this->rewardsService->getUserPoints($userId);
        $referralCode = $this->referralSystem->getOrCreateReferralCode($userId);
        $referralStats = $this->referralSystem->getUserReferralStats($userId);

        return view('rewards/index', [
            'userId'         => $userId,
            'points'         => $points,
            'stats'          => $stats,
            'referralCode'   => $referralCode,
            'referralStats' => $referralStats,
            'isPublic'       => $this->featureGate->isPublic('rewards_system'),
        ]);
    }

    /**
     * جلب/إنشاء كود الدعوة
     * GET /rewards/referral-code
     */
    public function getReferralCode(): ResponseInterface
    {
        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');

        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ]);
        }

        // التحقق من تفعيل الميزة
        if (!$this->featureGate->isEnabled('rewards_system', $userId, $userEmail)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'الميزة غير متاحة',
            ]);
        }

        $code = $this->referralSystem->getOrCreateReferralCode($userId);
        $referralUrl = 'https://auth.howplatform.net/register?ref=' . urlencode($code);

        return $this->response->setJSON([
            'success'      => true,
            'code'         => $code,
            'referral_url' => $referralUrl,
        ]);
    }

    /**
     * جلب سجل المعاملات
     * GET /rewards/transactions
     */
    public function getTransactions(): ResponseInterface
    {
        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');

        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ]);
        }

        // التحقق من تفعيل الميزة
        if (!$this->featureGate->isEnabled('rewards_system', $userId, $userEmail)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'الميزة غير متاحة',
            ]);
        }

        $limit = (int)$this->request->getGet('limit') ?: 10;
        $page = (int)$this->request->getGet('page') ?: 1;
        $offset = ($page - 1) * $limit;

        $transactions = $this->rewardsService->getUserTransactions($userId, $limit, $offset);
        
        // إصلاح الوصف للمعاملات القديمة من نوع friend_invite
        $transactions = $this->fixTransactionDescriptions($transactions);
        
        $totalCount = $this->rewardsService->getUserTransactionsCount($userId);
        $totalPages = (int)ceil($totalCount / $limit);

        return $this->response->setJSON([
            'success'      => true,
            'transactions' => $transactions,
            'pagination'   => [
                'current_page' => $page,
                'total_pages'  => $totalPages,
                'total_count'  => $totalCount,
                'limit'        => $limit,
            ],
        ]);
    }

    /**
     * جلب إحصائيات المستخدم
     * GET /rewards/stats
     */
    public function getStats(): ResponseInterface
    {
        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');

        log_message('debug', "[RewardsController::getStats] Called - userId: {$userId}, email: {$userEmail}");

        if ($userId <= 0) {
            log_message('warning', "[RewardsController::getStats] Unauthorized - userId: {$userId}");
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ]);
        }

        // التحقق من تفعيل الميزة
        $featureEnabled = $this->featureGate->isEnabled('rewards_system', $userId, $userEmail);
        log_message('debug', "[RewardsController::getStats] Feature gate check - enabled: " . ($featureEnabled ? 'true' : 'false'));
        
        if (!$featureEnabled) {
            log_message('warning', "[RewardsController::getStats] Feature not enabled for userId: {$userId}, email: {$userEmail}");
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'الميزة غير متاحة',
            ]);
        }

        $stats = $this->rewardsService->getUserStats($userId);
        $points = $this->rewardsService->getUserPoints($userId);

        log_message('debug', "[RewardsController::getStats] Success - userId: {$userId}, points: {$points}, stats: " . json_encode($stats));

        return $this->response->setJSON([
            'success' => true,
            'stats'   => $stats,
            'points'  => $points,
        ]);
    }

    /**
     * إصلاح الوصف للمعاملات القديمة من نوع friend_invite
     * استخراج اسم المستخدم من قاعدة auth
     */
    private function fixTransactionDescriptions(array $transactions): array
    {
        $db = \Config\Database::connect();
        
        foreach ($transactions as &$transaction) {
            // إذا كانت المعاملة من نوع friend_invite والوصف يحتوي على رقم
            if ($transaction['reward_type'] === 'friend_invite' && 
                !empty($transaction['description']) &&
                preg_match('/المستخدم #(\d+)/', $transaction['description'], $matches)) {
                
                $referredUserId = (int)$matches[1];
                
                // جلب اسم المستخدم من قاعدة auth
                try {
                    $authDb = \Config\Database::connect('remote');
                    $authUser = $authDb->query("
                        SELECT name 
                        FROM users 
                        WHERE id = ?
                    ", [$referredUserId])->getRowArray();
                    
                    if ($authUser && !empty($authUser['name'])) {
                        // استبدال رقم المستخدم بالاسم
                        $transaction['description'] = str_replace(
                            "المستخدم #{$referredUserId}",
                            $authUser['name'],
                            $transaction['description']
                        );
                    }
                } catch (\Exception $e) {
                    log_message('debug', 'Could not fetch user name for ID: ' . $referredUserId . ' - ' . $e->getMessage());
                }
            }
        }
        
        return $transactions;
    }
}

