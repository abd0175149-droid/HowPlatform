<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Services\RewardsService;
use App\Libraries\FeatureGate;

/**
 * RewardsFilter
 * تحديث تتبع الأيام المتتالية عند كل طلب للمستخدمين المسجلين دخول
 */
class RewardsFilter implements FilterInterface
{
    /**
     * التحقق من وجود الجداول المطلوبة
     */
    private function tablesExist(): bool
    {
        try {
            $db = \Config\Database::connect();
            $dbName = $db->getDatabase();
            
            $tables = [
                'rewards_users',
                'rewards_transactions',
                'rewards_referrals',
                'rewards_consecutive_days',
                'rewards_daily_logins'
            ];
            
            foreach ($tables as $table) {
                $result = $db->query("SHOW TABLES FROM `{$dbName}` LIKE '{$table}'");
                if (!$result->getRowArray()) {
                    return false;
                }
            }
            
            return true;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsFilter: Error checking tables: ' . $e->getMessage());
            return false;
        }
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // التحقق من وجود الجداول أولاً
        if (!$this->tablesExist()) {
            // الجداول غير موجودة - ربما لم يتم تشغيل Migration بعد
            // لا نعطل الطلبات، فقط نتجاهل
            return;
        }

        $session = session();
        $authUser = $session->get('auth_user') ?? [];
        $userId = (int)($authUser['id'] ?? 0);
        $userEmail = (string)($authUser['email'] ?? '');

        // إذا لم يكن المستخدم مسجل دخول، لا حاجة للمعالجة
        if ($userId <= 0) {
            return;
        }

        // التحقق من تفعيل الميزة
        try {
            $featureGate = new FeatureGate();
            if (!$featureGate->isEnabled('rewards_system', $userId, $userEmail)) {
                return; // الميزة غير مفعلة لهذا المستخدم
            }
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsFilter: FeatureGate error: ' . $e->getMessage());
            return;
        }

        // تحديث streak الأيام المتتالية
        try {
            $rewardsService = new RewardsService();
            $rewardsService->updateConsecutiveDays($userId);
        } catch (\Throwable $e) {
            // لا نريد أن يعطل Filter الطلبات في حالة حدوث خطأ
            // نستخدم debug level لتجنب ملء الـ logs بأخطاء متكررة
            log_message('debug', 'RewardsFilter::before error: ' . $e->getMessage());
        }

        return;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // not used
    }
}

