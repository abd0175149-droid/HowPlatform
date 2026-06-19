<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Models\DiplomaUserModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class TermsController extends BaseController
{
    protected DiplomaUserModel $diplomaUserModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->diplomaUserModel = new DiplomaUserModel();
        $this->userModel = new UserModel();
    }

    /**
     * التحقق من حالة الموافقة على الشروط
     * GET /students/terms/check
     */
    public function check(): ResponseInterface
    {
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);

        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'غير مسموح'
            ]);
        }

        $db = Database::connect();
        
        // التحقق من وجود الحقل terms_accepted
        $hasColumn = $this->columnExists('diploma_users', 'terms_accepted');
        
        if (!$hasColumn) {
            // الحقل غير موجود - نعتبر أن المستخدم موافق (توافق عكسي)
            return $this->response->setJSON([
                'success' => true,
                'terms_accepted' => true,
                'message' => 'الحقل غير موجود بعد في قاعدة البيانات'
            ]);
        }

        $diplomaUser = $db->table('diploma_users')
            ->select('terms_accepted, terms_accepted_at')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        if (!$diplomaUser) {
            // لا يوجد سجل في diploma_users
            return $this->response->setJSON([
                'success' => true,
                'terms_accepted' => false,
                'message' => 'لا يوجد سجل للمستخدم'
            ]);
        }

        $termsAccepted = (int)($diplomaUser['terms_accepted'] ?? 0) === 1;

        return $this->response->setJSON([
            'success' => true,
            'terms_accepted' => $termsAccepted,
            'terms_accepted_at' => $diplomaUser['terms_accepted_at'] ?? null
        ]);
    }

    /**
     * قبول الشروط والأحكام
     * POST /students/terms/accept
     */
    public function accept(): ResponseInterface
    {
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);

        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'غير مسموح'
            ]);
        }

        $db = Database::connect();
        
        // التحقق من وجود الحقل
        if (!$this->columnExists('diploma_users', 'terms_accepted')) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'الحقل غير موجود في قاعدة البيانات. يرجى تشغيل ملف SQL لإضافة الحقل.'
            ]);
        }

        try {
            $db->transStart();

            // تحديث حالة الموافقة
            $updated = $db->table('diploma_users')
                ->where('user_id', $uid)
                ->update([
                    'terms_accepted' => 1,
                    'terms_accepted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            if (!$updated) {
                // إن لم يوجد سجل، ننشئ واحد
                $db->table('diploma_users')
                    ->insert([
                        'user_id' => $uid,
                        'terms_accepted' => 1,
                        'terms_accepted_at' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('فشل تحديث قاعدة البيانات');
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'تم قبول الشروط والأحكام بنجاح'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Terms acceptance error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الموافقة: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * رفض الشروط والأحكام وحذف الحساب
     * POST /students/terms/reject
     */
    public function reject(): ResponseInterface
    {
        $authUser = session('auth_user') ?? [];
        $uid = (int)($authUser['id'] ?? 0);

        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'غير مسموح'
            ]);
        }

        $db = Database::connect();

        try {
            $db->transStart();

            // حذف سجل من diploma_users
            $db->table('diploma_users')
                ->where('user_id', $uid)
                ->delete();

            // ملاحظة: لا نحذف من جدول users لأن قد يكون هناك استخدامات أخرى
            // يمكن إضافة منطق إضافي هنا إذا لزم

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('فشل حذف البيانات');
            }

            // مسح الجلسة
            session()->destroy();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'تم حذف حسابك بنجاح',
                'redirect_url' => site_url('students/logout')
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Terms rejection error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الحساب: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * التحقق من وجود عمود في جدول
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $db = Database::connect();
            $dbName = $db->getDatabase();
            
            $query = $db->query("
                SELECT COUNT(*) as count
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
            ", [$dbName, $table, $column]);
            
            $result = $query->getRowArray();
            return (int)($result['count'] ?? 0) > 0;
        } catch (\Throwable $e) {
            log_message('error', 'Column exists check error: ' . $e->getMessage());
            return false;
        }
    }
}

