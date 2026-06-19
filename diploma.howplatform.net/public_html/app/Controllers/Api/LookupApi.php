<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PlanTypeModel;
use App\Models\PaymentMethodModel;

class LookupApi extends BaseController
{
    /**
     * GET /api/plans
     * يعيد الخطط الفعّالة + ميزاتها بشكل مناسب للواجهة.
     */
    public function plans()
    {
        // ⚠️ PlanTypeModel يجب أن يحتوي دالة getActivePlansWithFeatures() حسب ما أضفناه سابقاً
        $plans = model(PlanTypeModel::class)->getActivePlansWithFeatures();

        // توحيد المفاتيح لتتوافق مع واجهة JS
        $data = array_map(static function (array $p): array {
            return [
                'id'            => (int) $p['id'],
                'name'          => (string) $p['plan_type_name'], // ← name
                'price'         => (float) $p['amount'],          // ← price
                'duration_days' => (int) $p['duration_days'],
                'features'      => $p['features'] ?? [],
            ];
        }, $plans);

        return $this->response->setJSON(['data' => $data]);
    }

    /**
     * GET /api/payment-methods
     * يعيد جميع طرق الدفع (لا يوجد is_active في جدولك).
     */
    public function paymentMethods()
    {
        $methods = model(PaymentMethodModel::class)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->response->setJSON(['data' => $methods]);
    }
}
