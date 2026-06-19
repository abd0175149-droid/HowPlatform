<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PlanTypeModel;
use App\Models\PendingRequestModel;
use App\Models\PaymentModel;
use App\Models\PaymentMethodModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Upgrade as UpgradeConfig;

/**
 * منطق طلب/اعتماد ترقية الحساب إلى Premium
 * - يدعم وضع التطوير عبر Feature Gate
 * - يتحقق من صحة المدخلات
 * - يستخدم معاملات لضمان الذرّية
 */
class UpgradeApi extends BaseController
{
    // ============================================================
    // حارس "وضع التطوير": يسمح فقط للمستخدمين المحددين في Config\Upgrade
    // ============================================================
    private function guardDevOnly(int $userId): bool
    {
        // ⚠️ إن لم يوجد ملف الإعداد، اعتبر الميزة متاحة للجميع
        if (! class_exists(UpgradeConfig::class)) {
            return true;
        }

        $cfg = config(UpgradeConfig::class);
        if (! ($cfg->devOnly ?? false)) {
            return true; // الإنتاج: الميزة متاحة للجميع
        }

        // أثناء التطوير: نسمح فقط للمذكورين في القائمة (ID أو Email)
        $email = (string) (session('user_email') ?? '');

        if ($email === '') {
            // جلب من القاعدة كحل احتياطي
            $user  = model(UserModel::class)->select('email')->find($userId);
            $email = $user['email'] ?? '';
        }

        foreach ($cfg->allowedUsers as $needle) {
            if ((is_numeric($needle) && (int) $needle === $userId) ||
                (is_string($needle) && $needle === $email)) {
                return true;
            }
        }

        return false;
    }

    // ============================================================
    // حارس اعتماد الدفع: اسمح للأدمن أو لويبهوك موقّع
    // عدّل المنطق وفق نظام صلاحياتك
    // ============================================================
    private function guardAdminOrWebhook(): bool
    {
        // مثال بسيط: أدمن من السيشن
        if ((string) session('role') === 'admin') {
            return true;
        }

        // مثال Webhook: توكن من الهيدر يطابق قيمة في .env
        $token    = $this->request->getHeaderLine('X-Webhook-Token');
        $expected = env('webhook.upgrade_token');

        if ($expected && is_string($expected) && hash_equals($expected, (string) $token)) {
            return true;
        }

        return false;
    }

    // ============================================================
    // POST /api/upgrade/request
    // ينشئ pending_requests (+ payment pending إذا اخترت طريقة دفع)
    // ============================================================
    public function request()
    {
        $userId = (int) session('user_id');
        if (! $userId) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'غير مصرح']);
        }
        if (! $this->guardDevOnly($userId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'الميزة قيد الاختبار حالياً']);
        }

        $planTypeId = (int) ($this->request->getPost('plan_type_id') ?? 0);

        // ⚠️ لا تجبر طريقة الدفع على 0 — خليها اختيارية فعلاً
        $methodRaw = $this->request->getPost('payment_method_id');
        $methodId  = ($methodRaw === null || $methodRaw === '') ? null : (int) $methodRaw;

        if ($planTypeId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'plan_type_id مطلوب']);
        }

        // ✅ التحقق من الخطة (is_active + amount موجودة في جدولك)
        $plan = model(PlanTypeModel::class)->find($planTypeId);
        if (! $plan || (int) ($plan['is_active'] ?? 0) !== 1) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'الخطة غير متاحة']);
        }

        // منع إنشاء أكثر من طلب Pending للمستخدم
        $prModel = model(PendingRequestModel::class);
        $hasOpen = $prModel->where('user_id', $userId)->where('status', 'pending')->first();
        if ($hasOpen) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'لديك طلب قيد المعالجة']);
        }

        // تحقّق من طريقة الدفع إن تم تمريرها
        $method = null;
        if ($methodId !== null) {
            $method = model(PaymentMethodModel::class)->find($methodId);
            if (! $method) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'طريقة الدفع غير صحيحة']);
            }
        }

        // معاملة لضمان الذرّية بين إنشاء الطلب والدفع
        $db = db_connect();
        try {
            $db->transException(true)->transStart();

            // 1) إنشاء طلب Pending
            $pendingId = $prModel->insert([
                'user_id'      => $userId,
                'plan_type_id' => $planTypeId,
                'request_date' => Time::now()->toDateTimeString(), // يعتمد timezone من الإعداد العام
                'status'       => 'pending',
            ], true); // true لإرجاع ID

            // 2) إنشاء دفعة Pending (إن تم اختيار طريقة الدفع)
            $paymentId = null;
            if ($method !== null) {
                $payModel = model(PaymentModel::class);
                $invoice  = $this->generateInvoiceNumberUnique($payModel);

                $paymentId = $payModel->insert([
                    'user_id'               => $userId,
                    'amount'                => (float) $plan['amount'],
                    'payment_method_id'     => $methodId,
                    'status'                => 'pending',
                    'invoice_number'        => $invoice,
                    'gateway_transaction_id'=> null,
                    'payment_date'          => null,
                    'notes'                 => 'Premium upgrade request',
                    'recorded_by_admin_id'  => null,
                    'pending_requests_id'   => $pendingId,
                ], true);
            }

            $db->transComplete();

            return $this->response->setJSON([
                'ok'         => true,
                'pending_id' => (int) $pendingId,
                'payment_id' => $paymentId ? (int) $paymentId : null,
            ]);
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'Upgrade request failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'فشل إنشاء الطلب']);
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'Upgrade request error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'خطأ غير متوقع']);
        }
    }

    // ============================================================
    // POST /api/upgrade/confirm
    // يعتمد الدفعة ويمدّد premium_until ويضبط is_premium=1
    // (تحميه guardAdminOrWebhook)
    // ============================================================
    public function confirm()
    {
        // 🔐 حماية الاستدعاء
        if (! $this->guardAdminOrWebhook()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'غير مصرح (لوحة الإدارة/ويبهوك فقط)']);
        }

        $paymentId = (int) ($this->request->getPost('payment_id') ?? 0);
        if ($paymentId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'payment_id مطلوب']);
        }

        $db = db_connect();
        try {
            $db->transException(true)->transStart();

            $payModel = model(PaymentModel::class);
            $payment  = $payModel->find($paymentId);
            if (! $payment || $payment['status'] !== 'pending') {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'دفعة غير صالحة للتأكيد']);
            }

            $prModel   = model(PendingRequestModel::class);
            $planModel = model(PlanTypeModel::class);

            $pending = $prModel->find((int) $payment['pending_requests_id']);
            if (! $pending || $pending['status'] !== 'pending') {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'طلب غير صالح للاعتماد']);
            }

            $plan = $planModel->find((int) $pending['plan_type_id']);
            if (! $plan || (int) ($plan['is_active'] ?? 0) !== 1) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'الخطة غير متاحة للاعتماد']);
            }

            // 1) اعتماد الدفعة
            $payModel->update($paymentId, [
                'status'       => 'paid',
                'payment_date' => Time::now()->toDateTimeString(),
            ]);

            // 2) تحديث الطلب
            $prModel->update((int) $payment['pending_requests_id'], ['status' => 'approved']);

            // 3) حساب وتمديد تاريخ انتهاء الباقة
            $days  = (int) ($plan['duration_days'] ?? 0);
            if ($days <= 0) {
                $days = 30; // مدة احتياطية حتى لا يفشل الاعتماد
            }

            $userId = (int) $payment['user_id'];
            $user   = $db->table('users')->select('premium_until')->where('id', $userId)->get()->getRowArray();
            $today  = Time::today();

            $start = $today;
            if (! empty($user['premium_until'])) {
                $currentUntil = Time::parse($user['premium_until']);
                if ($currentUntil->toDateString() > $today->toDateString()) {
                    $start = $currentUntil; // تمديد من الحالي إن لم ينتهِ
                }
            }

            $newUntil = $start->addDays($days)->toDateString();

            // 4) ترقية المستخدم (Query Builder لتفادي قيود allowedFields)
            $db->table('users')->where('id', $userId)->update([
                'is_premium'    => 1,
                'premium_until' => $newUntil,
            ]);

            $db->transComplete();

            return $this->response->setJSON(['ok' => true, 'premium_until' => $newUntil]);
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'Upgrade confirm failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'فشل اعتماد الدفع']);
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'Upgrade confirm error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'خطأ غير متوقع']);
        }
    }

    // ============================================================
    // مولّد رقم فاتورة مع تحقّق تصادم (unique)
    // ============================================================
    private function generateInvoiceNumberUnique(PaymentModel $payModel): string
    {
        // نحاول عدّة مرات لاحتمال تصادم نادر جداً
        for ($i = 0; $i < 6; $i++) {
            $invoice = $this->generateInvoiceNumber();
            $exists  = $payModel->where('invoice_number', $invoice)->first();
            if (! $exists) {
                return $invoice;
            }
        }

        // إن فشلنا بعد محاولات: نرمي استثناء ليُعالج في المعاملة
        throw new \RuntimeException('تعذّر توليد رقم فاتورة فريد');
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
