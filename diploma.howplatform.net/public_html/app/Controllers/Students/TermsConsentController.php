<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Models\DiplomaUserModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class TermsConsentController extends BaseController
{
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setJSON($payload);
    }

    /**
     * @return array{0:int,1:array}
     */
    private function requireAuth(): array
    {
        $authUser = session('auth_user') ?? [];
        $uid      = (int) ($authUser['id'] ?? 0);

        if ($uid <= 0) {
            throw new \RuntimeException('Unauthenticated');
        }

        return [$uid, $authUser];
    }

    /**
     * التحقق من وجود عمود في جدول
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $db = \Config\Database::connect();
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

    public function status(): ResponseInterface
    {
        try {
            [$uid, $authUser] = $this->requireAuth();
        } catch (\RuntimeException $e) {
            return $this->json([
                'status'  => 'error',
                'message' => 'يجب تسجيل الدخول أولاً.',
            ], 401);
        }

        $db = \Config\Database::connect();
        $diplomaModel = new DiplomaUserModel();

        // التحقق من وجود الحقل terms_accepted
        $hasColumn = $this->columnExists('diploma_users', 'terms_accepted');
        
        if (!$hasColumn) {
            // الحقل غير موجود - نعتبر أن المستخدم موافق (توافق عكسي)
            return $this->json([
                'status' => 'ok',
                'data'   => [
                    'consented'    => true,
                    'consent_date' => null,
                    'user_name'    => (string) ($authUser['name'] ?? ''),
                ],
            ]);
        }

        // البحث في diploma_users أولاً (الأولوية)
        $diplomaRow = $db->table('diploma_users')
            ->select('terms_accepted, terms_accepted_at, consent_given, consent_date')
            ->where('user_id', $uid)
            ->get()
            ->getRowArray();

        $consented = false;
        $consentAt = null;

        if ($diplomaRow) {
            // التحقق من terms_accepted أولاً
            $termsAccepted = $diplomaRow['terms_accepted'] ?? null;
            
            if ($termsAccepted !== null) {
                // الحقل موجود - نستخدمه
                $consented = (int) $termsAccepted === 1;
                $consentAt = $diplomaRow['terms_accepted_at'] ?? null;
            } else {
                // الحقل غير موجود - نتحقق من consent_given القديم
                $oldConsent = (int) ($diplomaRow['consent_given'] ?? 0);
                
                if ($oldConsent === 1) {
                    // المستخدم وافق سابقاً على consent_given - ننقل الموافقة
                    $consented = true;
                    $consentAt = $diplomaRow['consent_date'] ?? null;
                    
                    // تحديث terms_accepted تلقائياً
                    $db->table('diploma_users')
                        ->where('user_id', $uid)
                        ->update([
                            'terms_accepted' => 1,
                            'terms_accepted_at' => $consentAt ?? date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    $consented = false;
                }
            }
            
            // Log للتصحيح
            log_message('debug', "Terms consent check - User: $uid, terms_accepted: " . ($termsAccepted ?? 'null') . ", consent_given: " . ($diplomaRow['consent_given'] ?? 'null') . ", consented: " . ($consented ? 'true' : 'false'));
        } else {
            // لا يوجد سجل - يعتبر لم يوافق بعد
            $consented = false;
            log_message('debug', "Terms consent check - User: $uid, no diploma_users record found");
        }

        return $this->json([
            'status' => 'ok',
            'data'   => [
                'consented'    => $consented,
                'consent_date' => $consentAt,
                'user_name'    => (string) ($authUser['name'] ?? ''),
            ],
        ]);
    }

    public function accept(): ResponseInterface
    {
        try {
            [$uid, $authUser] = $this->requireAuth();
        } catch (\RuntimeException $e) {
            return $this->json([
                'status'  => 'error',
                'message' => 'يجب تسجيل الدخول أولاً.',
            ], 401);
        }

        $now          = date('Y-m-d H:i:s');
        $db           = \Config\Database::connect();
        $diplomaModel = new DiplomaUserModel();

        // التحقق من وجود الحقل
        if (!$this->columnExists('diploma_users', 'terms_accepted')) {
            return $this->json([
                'status'  => 'error',
                'message' => 'الحقل غير موجود في قاعدة البيانات. يرجى تشغيل ملف SQL لإضافة الحقل.',
            ], 500);
        }

        $db->transStart();

        // تحديث terms_accepted في diploma_users
        $diplomaRow = $diplomaModel->where('user_id', $uid)->first();
        
        if ($diplomaRow && isset($diplomaRow['id'])) {
            // تحديث السجل الموجود
            $diplomaModel->update($diplomaRow['id'], [
                'terms_accepted' => 1,
                'terms_accepted_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            // محاولة التحديث باستخدام where
            $db->table('diploma_users')
                ->where('user_id', $uid)
                ->update([
                    'terms_accepted' => 1,
                    'terms_accepted_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->json([
                'status'  => 'error',
                'message' => 'تعذّر حفظ الموافقة. حاول مرة أخرى.',
            ], 500);
        }

        // تحديث الجلسة
        session()->set('auth_user', array_merge($authUser, [
            'terms_accepted' => 1,
            'terms_accepted_at' => $now,
        ]));

        return $this->json([
            'status'  => 'ok',
            'message' => 'تم تسجيل موافقتك بنجاح.',
        ]);
    }

    public function decline(): ResponseInterface
    {
        try {
            [$uid] = $this->requireAuth();
        } catch (\RuntimeException $e) {
            return $this->json([
                'status'  => 'error',
                'message' => 'يجب تسجيل الدخول أولاً.',
            ], 401);
        }

        $db           = \Config\Database::connect();
        
        $db->transStart();
        
        // حذف من diploma_users
        $db->table('diploma_users')->where('user_id', $uid)->delete();
        
        // حذف من users
        $db->table('users')->where('id', $uid)->delete();
        
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->json([
                'status'  => 'error',
                'message' => 'تعذّر حذف الحساب حالياً. حاول مرة أخرى.',
            ], 500);
        }

        helper('cookie');
        delete_cookie('howplatform_token');
        session()->destroy();

        $redirectBase = 'https://auth.howplatform.net/login';
        $redirect     = $redirectBase . '?redirect=' . rawurlencode(site_url('students'));

        return $this->json([
            'status'   => 'ok',
            'message'  => 'تم حذف حسابك بناءً على طلبك.',
            'redirect' => $redirect,
        ]);
    }
}

