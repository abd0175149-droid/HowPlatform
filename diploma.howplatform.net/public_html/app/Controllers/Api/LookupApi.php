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
        // وضع api: قائمة الخطط من Plans (المصدر الموثوق) مع fail-open للقراءة المحلّية.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->plansEnabled()) {
                $res = $client->plansList('diploma');
                if (is_array($res) && ($res['ok'] ?? false) && is_array($res['plans'] ?? null)) {
                    $data = array_map(static function (array $p): array {
                        $features = array_map(static function (array $f): array {
                            return [
                                'feature_key'  => (string) ($f['feature_key'] ?? ''),
                                'feature_text' => (string) ($f['feature_text'] ?? ''),
                                'limits_json'  => $f['limits_json'] ?? null,
                                'value'        => $f['value'] ?? null,
                            ];
                        }, is_array($p['features'] ?? null) ? $p['features'] : []);

                        return [
                            'id'            => (int) ($p['id'] ?? 0),
                            'name'          => (string) ($p['name'] ?? ''),
                            'price'         => (float) ($p['price'] ?? 0),
                            'duration_days' => (int) ($p['duration_days'] ?? 0),
                            'features'      => $features,
                        ];
                    }, $res['plans']);

                    return $this->response->setJSON(['data' => $data]);
                }
                // Plans غير متاح → تابع للقراءة المحلّية (fail-open)
            }
        } catch (\Throwable $e) {
            log_message('warning', 'LookupApi::plans Plans failed: ' . $e->getMessage());
        }

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
