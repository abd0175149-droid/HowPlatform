<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class VoucherStore extends BaseController
{
    use ResponseTrait;

    /**
     * نقطة دخول عامة لتجميع POST/DELETE في دالة واحدة (حسب الميثود).
     */
    public function index()
    {
        $method = strtoupper($this->request->getMethod());
        if ($method === 'POST')  return $this->store();
        if ($method === 'DELETE') return $this->remove();

        return $this->fail('Method Not Allowed. Use POST (create) or DELETE (cleanup).', 405);
    }

    /**
     * حفظ الفاوتشر في قاعدة بيانات منصة الدبلوم.
     * الحقول المتوقعة (JSON/FORM):
     *  code, plan_id, duration_days, plan_type_id,
     *  buyer_name, buyer_email, buyer_phone, notes,
     *  issued_at, expires_at,
     *  is_redeemed, redeemed_by_user_id, redeemed_at, redeemed_ip, redeemed_ua
     */
    protected function store()
    {
        $data = $this->getIncomingData();

        // 1) التحقق الأساسي
        $required = ['code','plan_id','duration_days','plan_type_id','issued_at','expires_at'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $data) || $data[$k] === null || $data[$k] === '') {
                return $this->failValidationErrors("الحقل {$k} إجباري.");
            }
        }

        // نمط الكود: 3 أحرف (كب/سمول) + 6 أرقام
        if (!preg_match('/^[A-Za-z]{3}\d{6}$/', (string)$data['code'])) {
            return $this->failValidationErrors('صيغة الكود غير صحيحة. يجب أن تكون 3 أحرف + 6 أرقام.');
        }

        // التواريخ بصيغة صحيحة
        $issuedAt  = $this->normalizeDatetime($data['issued_at']);
        $expiresAt = $this->normalizeDatetime($data['expires_at']);
        if (!$issuedAt || !$expiresAt) {
            return $this->failValidationErrors('issued_at / expires_at يجب أن تكونا تواريخ/أوقات صحيحة (Y-m-d H:i:s).');
        }

        // 2) تجهيز السجل للإدراج
        $row = [
            'code'                 => (string) $data['code'],
            'plan_id'              => (int)    $data['plan_id'],
            'duration_days'        => (int)    $data['duration_days'],
            'plan_type_id'         => (int)    $data['plan_type_id'],
            'buyer_name'           => $this->nullableTrim($data['buyer_name'] ?? null, 255),
            'buyer_email'          => $this->nullableTrim($data['buyer_email'] ?? null, 255),
            'buyer_phone'          => $this->nullableTrim($data['buyer_phone'] ?? null, 50),
            'notes'                => $this->nullableTrim($data['notes'] ?? null, 2000),
            'issued_at'            => $issuedAt,
            'expires_at'           => $expiresAt,
            'is_redeemed'          => (int)    ($data['is_redeemed'] ?? 0),
            'redeemed_by_user_id'  => $this->nullableInt($data['redeemed_by_user_id'] ?? null),
            'redeemed_at'          => $this->normalizeDatetime($data['redeemed_at'] ?? null),
            'redeemed_ip'          => $this->nullableTrim($data['redeemed_ip'] ?? null, 64),
            'redeemed_ua'          => $this->nullableTrim($data['redeemed_ua'] ?? null, 255),
        ];

        // 3) تحقق التعارض (كود فريد)
        $db = Database::connect();
        $builder = $db->table('vouchers');

        $exists = $builder->where('code', $row['code'])->countAllResults();
        if ($exists > 0) {
            return $this->failResourceExists('الكود موجود مسبقًا.', 409);
        }

        // (اختياري) التحقق من وجود الخطة plan_id في جدول plan_types
        // إن رغبت: فعّل السطور التالية:
        // $planExists = $db->table('plan_types')->where('id', $row['plan_id'])->countAllResults();
        // if (!$planExists) { return $this->failValidationErrors('plan_id غير معروف.'); }

        // 4) الإدراج داخل ترانزاكشن
        try {
            $db->transStart();
            $builder->insert($row);
            $id = $db->insertID();
            $db->transComplete();

            if (!$db->transStatus()) {
                return $this->failServerError('تعذر حفظ الفاوتشر (فشل المعاملة).');
            }
        } catch (\Throwable $e) {
            return $this->failServerError('تعذر حفظ الفاوتشر: '.$e->getMessage());
        }

        // 5) نجاح
        return $this->respond([
            'status'  => 'ok',
            'id'      => $id,
            'code'    => $row['code'],
            'message' => 'تم حفظ الفاوتشر بنجاح.'
        ], 201);
    }

    /**
     * حذف فاوتشر (للتنظيف) عبر ?code=... أو JSON {"code": "..."}
     * يعيد 200 دائمًا إن تم الحذف أو لم يوجد السجل (idempotent).
     */
    protected function remove()
    {
        $code = $this->request->getGet('code');
        if (!$code) {
            $json = $this->request->getJSON(true);
            $code = $json['code'] ?? null;
        }
        if (!$code) {
            return $this->failValidationErrors('code مطلوب للحذف.');
        }

        $db = Database::connect();
        $builder = $db->table('vouchers');

        try {
            $deleted = $builder->where('code', $code)->delete();
        } catch (\Throwable $e) {
            // حتى لو فشل، نعيد رسالة مفيدة
            return $this->failServerError('تعذر حذف الفاوتشر: '.$e->getMessage());
        }

        return $this->respond([
            'status'  => 'ok',
            'deleted' => (bool) $deleted,
        ], 200);
    }

    // ----------------- Helpers -----------------

    protected function getIncomingData(): array
    {
        // ترتيب تفضيل: JSON ثم form ثم raw
        $json = $this->request->getJSON(true);
        if (is_array($json) && !empty($json)) return $json;

        $post = $this->request->getPost();
        if (is_array($post) && !empty($post)) return $post;

        $raw = $this->request->getRawInput();
        return is_array($raw) ? $raw : [];
    }

    protected function normalizeDatetime($value): ?string
    {
        if ($value === null || $value === '') return null;
        // اقبل صيغة Y-m-d H:i:s أو أي صيغة تقبلها strtotime
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$value)) {
            return (string)$value;
        }
        $ts = strtotime((string)$value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    protected function nullableTrim($val, int $maxLen = 255): ?string
    {
        if ($val === null) return null;
        $val = trim((string)$val);
        if ($val === '') return null;
        return mb_substr($val, 0, $maxLen);
    }

    protected function nullableInt($val): ?int
    {
        if ($val === null || $val === '') return null;
        return (int)$val;
    }
}
