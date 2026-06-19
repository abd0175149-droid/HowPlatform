<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PendingRequestModel;
use App\Models\PlanTypeModel;
use App\Models\PaymentModel;
use App\Models\PaymentMethodModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\I18n\Time;

class UpgradeService
{
    /**
     * إنشاء طلب ترقية مع دفعة "pending" اختيارية.
     *
     * @return array{pending_id:int, payment_id:int|null}
     */
    public function createPending(int $userId, int $planTypeId, ?int $paymentMethodId = null): array
    {
        $db        = db_connect();
        $prModel   = model(PendingRequestModel::class);
        $planModel = model(PlanTypeModel::class);
        $payModel  = model(PaymentModel::class);

        // ✅ التحقق من الخطة وحالة التفعيل
        $plan = $planModel->find($planTypeId);
        if (! $plan || (int) $plan['is_active'] !== 1) {
            throw new \InvalidArgumentException('الخطة غير متاحة للشراء حالياً');
        }

        // ✅ منع تكرار طلب معلق لنفس المستخدم
        $hasOpen = $prModel->where('user_id', $userId)->where('status', 'pending')->first();
        if ($hasOpen) {
            throw new \RuntimeException('لديك طلب قيد المعالجة');
        }

        // ✅ التحقق من طريقة الدفع إن طُلِبت
        $method = null;
        if ($paymentMethodId !== null && $paymentMethodId > 0) {
            $method = model(PaymentMethodModel::class)->find($paymentMethodId);
            if (! $method) {
                throw new \InvalidArgumentException('طريقة الدفع غير صحيحة');
            }
        }

        try {
            $db->transException(true)->transStart();

            // 1) إنشاء الطلب
            $pendingId = $prModel->insert([
                'user_id'      => $userId,
                'plan_type_id' => $planTypeId,
                'request_date' => Time::now()->toDateTimeString(),
                'status'       => 'pending',
            ], true);

            // 2) إنشاء الدفعة (اختياري)
            $paymentId = null;
            if ($method) {
                $invoice = $this->generateUniqueInvoice($payModel);

                $paymentId = $payModel->insert([
                    'user_id'               => $userId,
                    'amount'                => (float) $plan['amount'], // من plan_types.amount
                    'payment_method_id'     => $paymentMethodId,
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

            return [
                'pending_id' => (int) $pendingId,
                'payment_id' => $paymentId ? (int) $paymentId : null,
            ];
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'UpgradeService::createPending DB error: ' . $e->getMessage());
            throw new \RuntimeException('فشل إنشاء الطلب');
        } catch (\Throwable $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'UpgradeService::createPending error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * اعتماد الدفعة وتحديث اشتراك المستخدم (يُستدعى من الأدمن أو Webhook).
     * - يغيّر payments.status = paid + payment_date
     * - يغيّر pending_requests.status = approved
     * - يحسب users.premium_until بإضافة duration_days من الخطة.
     */
    public function confirmPayment(int $paymentId): array
    {
        $db        = db_connect();
        $payModel  = model(PaymentModel::class);
        $prModel   = model(PendingRequestModel::class);
        $planModel = model(PlanTypeModel::class);

        // جلب الدفعة
        $payment = $payModel->find($paymentId);
        if (! $payment || $payment['status'] !== 'pending') {
            throw new \InvalidArgumentException('دفعة غير صالحة للتأكيد');
        }

        // جلب الطلب المرتبط
        $pending = $prModel->find((int) $payment['pending_requests_id']);
        if (! $pending || $pending['status'] !== 'pending') {
            throw new \InvalidArgumentException('طلب مرتبط غير صالح');
        }

        // جلب الخطة لحساب المدة
        $plan = $planModel->find((int) $pending['plan_type_id']);
        if (! $plan || (int) $plan['is_active'] !== 1) {
            throw new \InvalidArgumentException('الخطة غير متاحة للاعتماد');
        }

        $userId = (int) $payment['user_id'];
        $days   = (int) $plan['duration_days'];

        try {
            $db->transException(true)->transStart();

            // 1) اعتماد الدفعة
            $payModel->update($paymentId, [
                'status'       => 'paid',
                'payment_date' => Time::now()->toDateTimeString(),
            ]);

            // 2) اعتماد الطلب
            $prModel->update((int) $payment['pending_requests_id'], ['status' => 'approved']);

            // 3) تحديث اشتراك المستخدم (مباشرة عبر الـ Query Builder لأن UserModel لا يسمح بهذه الحقول)
            $user = $db->table('users')->select('premium_until')->where('id', $userId)->get()->getRowArray();
            $today = Time::today();
            $start = (! empty($user['premium_until']) && $user['premium_until'] > $today->toDateString())
                ? Time::createFromFormat('Y-m-d', $user['premium_until'])
                : $today;

            $newUntil = $start->addDays($days)->toDateString();

            $db->table('users')->where('id', $userId)->update([
                'is_premium'    => 1,
                'premium_until' => $newUntil,
            ]);

            $db->transComplete();

            return ['ok' => true, 'premium_until' => $newUntil];
        } catch (DatabaseException $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            log_message('error', 'UpgradeService::confirmPayment DB error: ' . $e->getMessage());
            throw new \RuntimeException('تعذر اعتماد الدفع');
        }
    }

    /** توليد رقم فاتورة فريد (payments.invoice_number فريد) */
    private function generateUniqueInvoice(PaymentModel $payModel): string
    {
        for ($i = 0; i < 6; $i++) {
            $invoice = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            if (! $payModel->where('invoice_number', $invoice)->first()) {
                return $invoice;
            }
        }
        throw new \RuntimeException('تعذّر توليد رقم فاتورة فريد');
    }
}
