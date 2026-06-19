<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\PlanModel; // ✅ نستخدم الموديل كما هو بدون تعديلات

/**
 * API للخطط — بديل عن PlansMeta لتجنّب التضارب.
 */
class PlansApi extends BaseController
{
    /**
     * GET /admin/api/plans
     * تُعيد قائمة الخطط لاستهلاك الواجهة (plans.js)
     */
    public function index()
    {
        try {
            $model = new PlanModel();

            // إن كان لديك دالة مخصصة بالموديل (مثلاً listAll/listActive) استخدمها:
            // $rows = $model->listAll();
            // وإن لم توجد، استخدم findAll() أو وفق حاجتك:
            $rows = $model->orderBy('id', 'DESC')->findAll();

            return $this->response->setJSON([
                'success' => true,
                'data'    => $rows,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'PlansApi index error: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'تعذّر تحميل البيانات.',
            ]);
        }
    }

    // (اختياري) تفاصيل/إنشاء/تحديث/حذف إن كانت الواجهة تستخدمها لاحقًا:
    // public function show(int $id) { ... }
    // public function store() { ... }
    // public function update(int $id) { ... }
    // public function delete(int $id) { ... }
}
