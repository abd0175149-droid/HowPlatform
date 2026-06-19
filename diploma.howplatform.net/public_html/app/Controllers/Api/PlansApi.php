<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PlanTypeModel;

class PlansApi extends BaseController
{
    public function index()
    {
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
