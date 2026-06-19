<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Throwable;

class AdminsStats extends Controller
{
    public function index(): ResponseInterface
    {
        try {
            $db = \Config\Database::connect();

            $now        = Time::now();
            $oneMonthAgo= $now->subMonths(1)->toDateTimeString();

            // إجمالي المستخدمين
            $totalUsers = (int) $db->table('users')->countAllResults();

            // نشطون خلال شهر
            $activeUsers = (int) $db->table('users')
                ->where('last_login_at >=', $oneMonthAgo)
                ->countAllResults();

            // غير نشطين (شهر مضى أو NULL)
            $inactiveUsers = (int) $db->table('users')
                ->groupStart()
                    ->where('last_login_at <', $oneMonthAgo)
                    ->orWhere('last_login_at IS NULL', null, false)
                ->groupEnd()
                ->countAllResults();

            // مميزون
            $premiumUsers = (int) $db->table('users')
                ->where('is_premium', 1)
                ->countAllResults();

            // عاديون = الإجمالي - المميزين (أدق وأسرع)
            $normalUsers  = max(0, $totalUsers - $premiumUsers);

            // توزيع حسب الجامعات
            $results = $db->table('users u')
                ->select('COALESCE(uni.name, "غير محدد") AS university, COUNT(u.id) AS total', false)
                ->join('universities uni', 'u.university_id = uni.id', 'left')
                ->groupBy('university')
                ->orderBy('total', 'DESC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'activity' => [
                    'active'   => $activeUsers,
                    'inactive' => $inactiveUsers,
                ],
                'premium' => [
                    'premium' => $premiumUsers,
                    'normal'  => $normalUsers,
                ],
                'universities' => $results,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'admin stats failed', 'error' => $e->getMessage()]);
        }
    }
}
