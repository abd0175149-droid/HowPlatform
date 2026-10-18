<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PlanTypeModel;

class PlansApi extends BaseController
{
    public function index()
    {
        // وضع api: قائمة الخطط من Plans (المصدر الموثوق) مع fail-open للقراءة المحلّية.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->plansEnabled()) {
                $res = $client->plansList('diploma');
                if (is_array($res) && ($res['ok'] ?? false) && is_array($res['plans'] ?? null)) {
                    $data = array_map(static function (array $p): array {
                        $features = array_map(
                            static fn(array $f): string => (string) ($f['feature_text'] ?? ''),
                            is_array($p['features'] ?? null) ? $p['features'] : []
                        );
                        return [
                            'id'            => (int) ($p['id'] ?? 0),
                            'name'          => (string) ($p['name'] ?? 'Plan'),
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
            log_message('warning', 'PlansApi::index Plans failed: ' . $e->getMessage());
        }

        $planModel = new PlanTypeModel();

        $plans = $planModel->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        // لو عندك جدول plan_type_features فعّل الكود التالي:
        $featuresMap = [];
        if (class_exists(\App\Models\PlanTypeFeatureModel::class)) {
            $featureModel = new \App\Models\PlanTypeFeatureModel();
            foreach ($featureModel->orderBy('display_order', 'ASC')->findAll() as $f) {
                $featuresMap[$f['plan_type_id']][] = $f['feature_text'];
            }
        }

        $data = array_map(static function ($p) use ($featuresMap) {
            return [
                'id'            => (int) $p['id'],
                'name'          => $p['plan_type_name'] ?? $p['name'] ?? 'Plan',
                'price'         => (float) $p['amount'],
                'duration_days' => (int) $p['duration_days'],
                'features'      => $featuresMap[$p['id']] ?? [],
            ];
        }, $plans);

        return $this->response->setJSON(['data' => $data]);
    }
}
