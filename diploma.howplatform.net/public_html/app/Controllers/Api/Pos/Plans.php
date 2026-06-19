<?php namespace App\Controllers\Api\Pos;

use App\Controllers\BaseController;
use App\Models\PlanTypeModel;
use App\Models\PlanTypeFeatureModel;

class Plans extends BaseController
{
    public function index()
    {
        // CORS بسيط للتجربة (عدّل origins لاحقًا)
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
                       ->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                       ->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(204);
        }

        $planModel    = new PlanTypeModel();
        $featureModel = new PlanTypeFeatureModel();

        // 1) جلب الخطط الفعّالة مرتّبة
        $plans = $planModel->getActiveOrdered(); // يعتمد على is_active + sort_order

        // 2) جلب المزايا لهذه الخطط دفعة واحدة
        $ids = array_column($plans, 'id');
        $featuresMap = $featureModel->getByPlanIds($ids);

        // 3) تجهيز الإخراج وفق صيغة واجهة POS
        $currency = 'JOD'; // يمكنك قراءتها من إعدادات المنصة لاحقًا
        $out = [];
        foreach ($plans as $p) {
            $popular = ((int)$p['sort_order'] <= 50); // معيار مبدئي (قابل للتغيير)
            $out[] = [
                'id'            => (int)$p['id'],
                'name'          => $p['plan_type_name'],
                'price_amount'  => (float)$p['amount'],
                'price_currency'=> $currency,
                'duration_days' => (int)$p['duration_days'],
                'popular'       => $popular,
                'features'      => $featuresMap[$p['id']] ?? [], // [{text,display_order}, ..]
            ];
        }

        // 4) كاش خفيف (اختياري)
        // cache()->save('diploma_pos_plans', $out, 300);

        return $this->response->setJSON($out);
    }
}
