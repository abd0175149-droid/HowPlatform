<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PaymentMethodModel;
use App\Models\PaymentModel;
use App\Models\PlanTypeModel;
use App\Models\UserProfileModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PendingRequests extends BaseController
{
    use ResponseTrait; // يوفّر respond() و fail*()

    /* =========================================================
     *                     صفحة إدارة الطلبات
     * ========================================================= */
    public function index()
    {
        return view('admins/pages/pending_requests');
    }

    /* =========================================================
     *                  جلب الطلبات مع الترقيم
     * ========================================================= */
    public function fetch()
    {
        /* ─── مدخلات الفلترة ─── */
        $status     = $this->request->getGet('status');
        $username   = $this->request->getGet('username');
        $phone      = $this->request->getGet('phone');
        $planTypeId = $this->request->getGet('plan_type_id');

        /* ─── معلمات الترقيم ─── */
        $perPage = max((int) ($this->request->getGet('per_page') ?? 10), 1);
        $page    = max((int) ($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        /* ─── الاستعلام الأساسي ─── */
        $db   = \Config\Database::connect();
        $base = $db->table('pending_requests')
            ->select(
                'pending_requests.*,
                 users.name,
                 users.phone_number,
                 plan_types.plan_type_name,
                 plan_types.amount AS plan_amount'
            )
            ->join('users', 'users.id = pending_requests.user_id')
            ->join('plan_types', 'plan_types.id = pending_requests.plan_type_id');

        /* تطبيق الفلاتر */
        if ($status && $status !== 'all') {
            $base->where('pending_requests.status', $status);
        }
        if ($username) {
            $base->like('users.name', $username);
        }
        if ($phone) {
            $base->like('users.phone_number', $phone);
        }
        if ($planTypeId) {
            $base->where('pending_requests.plan_type_id', $planTypeId);
        }

        /* ─── جلب كل السجلات لحساب الإحصاءات ─── */
        $allRequests  = (clone $base)->orderBy('pending_requests.request_date', 'DESC')->get()->getResultArray();
        $totalRecords = count($allRequests);
        $totalPages   = (int) ceil($totalRecords / $perPage);

        /* ─── حساب الإحصاءات ─── */
        $stats        = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_revenue' => 0];
        $paymentModel = new PaymentModel();

        foreach ($allRequests as $row) {
            $stats[$row['status']]++;

            $payment = $paymentModel
                ->where('pending_requests_id', $row['id'])
                ->where('status', 'paid')
                ->orderBy('payment_date', 'DESC')
                ->first();

            if ($payment) {
                $stats['total_revenue'] += (float) $payment['amount'];
            }
        }

        /* ─── جلب صفحة واحدة فقط ─── */
        $pageRequests = $base
            ->orderBy('pending_requests.request_date', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        /* ─── تهيئة البيانات للواجهة ─── */
        $formatted = [];
        foreach ($pageRequests as $row) {
            $payment = $paymentModel
                ->where('pending_requests_id', $row['id'])
                ->where('status', 'paid')
                ->orderBy('payment_date', 'DESC')
                ->first();

            $formatted[] = [
                'id'            => $row['id'],
                'user_id'       => $row['user_id'],
                'plan_type_id'  => $row['plan_type_id'],   // يأتي من pending_requests.*
                'name'          => $row['name'],
                'phone_number'  => $row['phone_number'],
                'plan'          => $row['plan_type_name'],
                'created_at'    => date('d/m/Y', strtotime($row['request_date'])),
                'status'        => $row['status'],
                'notes'         => $payment['notes'] ?? null,
                'amount'        => (float) $row['plan_amount'],
            ];
        }

        return $this->response->setJSON([
            'requests'   => $formatted,
            'stats'      => $stats,
            'pagination' => [
                'current_page'  => $page,
                'per_page'      => $perPage,
                'total_records' => $totalRecords,
                'total_pages'   => $totalPages,
            ],
        ]);
    }

    /* =========================================================
     *              حساب تاريخ نهاية الترقية افتراضيًا
     * ========================================================= */
    private function calculatePremiumUntil(int $planTypeId): string
    {
        $today = new \DateTime();
        $month = (int) $today->format('n');
        $year  = (int) $today->format('Y');

        // خطة سنوية
        if ($planTypeId === 1) {
            if ($month >= 7) {
                $year++;
            }
            return "$year-06-30";
        }

        // خطة فصلية
        if ($month >= 6) {  // من يونيو إلى ديسمبر
            return ($year + 1) . '-02-01';
        }

        return "$year-06-30"; // يناير إلى مايو
    }

    /* =========================================================
     *               قبول الطلب + تسجيل الدفعة
     * ========================================================= */
    public function approve($id = null)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('طلب غير مصرح به');
        }

        $data = $this->request->getJSON(true);
        $db   = \Config\Database::connect();
        $db->transStart();

        /* جلب الطلب */
        $request = $db->table('pending_requests')->where('id', $id)->get()->getRowArray();
        if (! $request) {
            return $this->failNotFound('الطلب غير موجود');
        }

        $userId = $request['user_id'];
        $planId = $request['plan_type_id'];

        /* تاريخ انتهاء الترقية */
        $premiumUntil = $this->calculatePremiumUntil($planId);

        /* تحديث المستخدم */
        $db->table('users')->where('id', $userId)->update([
            'is_premium'    => 1,
            'premium_until' => $premiumUntil,
        ]);

        /* تحديث حالة الطلب */
        $db->table('pending_requests')->where('id', $id)->update(['status' => 'approved']);

        /* إدراج / تحديث الدفعة */
        $paymentModel = new PaymentModel();
        $existing     = $paymentModel
            ->where('pending_requests_id', $id)
            ->where('status', 'paid')
            ->first();

        $paymentData = [
            'user_id'                => $userId,
            'pending_requests_id'    => $id,
            'amount'                 => $data['amount'],
            'payment_method_id'      => $data['payment_method_id'],
            'status'                 => 'paid',
            'invoice_number'         => $data['invoice_number'],
            'gateway_transaction_id' => $data['gateway_transaction_id'],
            'payment_date'           => $data['payment_date'],
            'notes'                  => $data['notes'] ?? null,
            'recorded_by_admin_id'   => session('user_id'),
        ];

        if ($existing) {
            $paymentModel->update($existing['id'], $paymentData);
        } else {
            $paymentModel->insert($paymentData);
        }

        $db->transComplete();

        return $this->respond([
            'success' => $db->transStatus(),
            'message' => 'تم قبول الطلب وتحديث الحساب بنجاح',
        ]);
    }

    /* =========================================================
     *                  جلب طرق الدفع
     * ========================================================= */
    public function paymentMethods()
    {
        return $this->respond((new PaymentMethodModel())->findAll());
    }

    /* =========================================================
     *               رفض الطلب + تصفير المبلغ
     * ========================================================= */
    public function reject($id = null)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('طلب غير مصرح به');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $request = $db->table('pending_requests')->where('id', $id)->get()->getRowArray();
        if (! $request) {
            return $this->failNotFound('الطلب غير موجود');
        }
        if ($request['status'] !== 'pending') {
            return $this->failValidationError('لا يمكن رفض طلب حالته ليست معلقة');
        }

        $db->table('pending_requests')->where('id', $id)->update(['status' => 'rejected']);

        (new PaymentModel())
            ->where('pending_requests_id', $id)
            ->where('status', 'paid')
            ->set('amount', 0)
            ->update();

        $db->transComplete();

        return $this->respond([
            'success' => $db->transStatus(),
            'message' => 'تم رفض الطلب وتصفير المبلغ بنجاح',
        ]);
    }

    /* =========================================================
     *              عرض تفاصيل طلب واحد
     * ========================================================= */
    public function show($id = null)
    {
        $db  = \Config\Database::connect();
        $req = $db->table('pending_requests pr')
            ->select(
                'pr.*,
                 u.name           AS user_name,
                 p.plan_type_name AS plan_name,
                 pay.amount,
                 pay.payment_method_id,
                 pay.invoice_number,
                 pay.gateway_transaction_id,
                 pay.payment_date,
                 pay.notes,
                 u.premium_until'
            )
            ->join('users u',      'u.id = pr.user_id')
            ->join('plan_types p', 'p.id = pr.plan_type_id')
            ->join('payments pay', 'pay.pending_requests_id = pr.id', 'left')
            ->where('pr.id', $id)
            ->orderBy('pay.payment_date', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (! $req) {
            return $this->failNotFound('الطلب غير موجود');
        }

        return $this->respond(['success' => true, 'data' => $req]);
    }

    /* =========================================================
     *               إعادة الطلب إلى معلّق
     * ========================================================= */
    public function revert($id = null)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('طلب غير مصرح به');
        }

        $updated = \Config\Database::connect()
            ->table('pending_requests')
            ->where('id', $id)
            ->update(['status' => 'pending']);

        return $this->respond([
            'success' => (bool) $updated,
            'message' => $updated
                ? 'تمت إعادة الطلب إلى الحالة معلّقة'
                : 'فشل في إعادة الطلب',
        ]);
    }

    /* =========================================================
     *               تحديث/إضافة ملاحظة الطلب
     * ========================================================= */
    public function updateNote($id = null)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $payload = $this->request->getJSON(true);
        $notes   = trim($payload['notes'] ?? '');

        $paymentModel = new PaymentModel();

        $existing = $paymentModel
            ->where('pending_requests_id', $id)
            ->where('status', 'paid')
            ->orderBy('payment_date', 'DESC')
            ->first();

        if ($existing) { // تحديث
            $updated = $paymentModel->update($existing['id'], ['notes' => $notes]);

            return $updated
                ? $this->respond(['success' => true, 'message' => 'تم حفظ الملاحظة'])
                : $this->failServerError('فشل في تحديث الملاحظة');
        }

        /* لا توجد دفعة سابقة: إنشاء سجلّ جديد بقيمة amount=0 */
        $request = \Config\Database::connect()
            ->table('pending_requests')
            ->select('user_id')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        $insertId = $paymentModel->insert([
            'pending_requests_id'    => $id,
            'user_id'                => $request['user_id'] ?? null,
            'amount'                 => 0,
            'payment_method_id'      => 1,
            'status'                 => 'paid',
            'payment_date'           => date('Y-m-d H:i:s'),
            'notes'                  => $notes,
            'recorded_by_admin_id'   => session('user_id') ?? null,
        ]);

        return $insertId
            ? $this->respond(['success' => true, 'message' => 'تم إنشاء سجل الدفع مع الملاحظة'])
            : $this->failServerError('فشل في إنشاء سجل الدفع');
    }

    /* =========================================================
     *                  جلب بيانات مستخدم مختصرة
     * ========================================================= */
    public function contact($id = null)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('طلب غير مصرح به');
        }

        $user = (new UserProfileModel())->find($id);
        if (! $user) {
            return $this->failNotFound('المستخدم غير موجود');
        }

        return $this->respond([
            'success' => true,
            'data'    => [
                'id'           => $user['id'],
                'name'         => $user['name'],
                'phone_number' => $user['phone_number'],
                'email'        => $user['email'],
            ],
        ]);
    }

    /* =========================================================
     *                 إرسال بريد الطلب – emailUser()
     * ========================================================= */
    public function emailUser()
    {
        $data = $this->request->getJSON(true);

        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }
        if (empty($data['user_id']) || empty($data['plan_type_id'])) {
            return $this->fail('معلومات ناقصة', 422);
        }

        $user = (new UserProfileModel())->find((int) $data['user_id']);
        if (! $user) {
            return $this->failNotFound('المستخدم غير موجود');
        }

        $plan = (new PlanTypeModel())->find((int) $data['plan_type_id']);
        if (! $plan) {
            return $this->failNotFound('الخطة غير موجودة');
        }

        $methods = '
          <ul style="margin:0;padding-right:20px;list-style:square">
            <li>كلك: <strong>abdulrzaqa</strong></li>
            <li>محفظة: <strong>0789154719</strong></li>
          </ul>';

        $html = view('emails/upgrade_request', [
            'name'      => $user['name'],
            'planName'  => $plan['plan_type_name'],
            'amount'    => $plan['amount'],
            'methods'   => $methods,
        ]);

        $email = Services::email();
        $email->setTo($user['email']);
        $email->setSubject('رسوم الترقية – منصة كيف؟');
        $email->setMessage($html);

        if (! $email->send()) {
            log_message('error', 'Email error: '.$email->printDebugger());
            return $this->failServerError('فشل في إرسال البريد');
        }

        return $this->respond(['success' => true, 'message' => 'تم إرسال البريد']);
    }
}
