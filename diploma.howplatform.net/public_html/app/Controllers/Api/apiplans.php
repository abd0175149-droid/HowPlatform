<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Throwable;

class apiplans extends ResourceController
{
    protected $format = 'json';

    public function index(): ResponseInterface
    {
        try {
            $db = Database::connect();

            // 1) جلب الخطط الفعّالة مرتّبة حسب level ثم price
            $plans = $db->table('plans')
                ->select('id, name, level, default_duration_days, price, is_active')
                ->where('is_active', 1)
                ->orderBy('level', 'ASC')
                ->orderBy('price', 'ASC')
                ->get()
                ->getResultArray();

            if (!$plans) {
                return $this->respond([
                    'status' => 'ok',
                    'data'   => [],
                ]);
            }

            // 2) جمع معرفات الخطط لجلب مزاياها
            $planIds = array_map('intval', array_column($plans, 'id'));

            // 3) جلب المزايا من plan_features (الأعمدة: id, plan_id, feature_key, limits_json)
            $featuresByPlan = [];
            if (!empty($planIds)) {
                $rows = $db->table('plan_features')
                    ->select('plan_id, feature_key, limits_json')
                    ->whereIn('plan_id', $planIds)
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($rows as $r) {
                    $pid = (int) $r['plan_id'];
                    $featuresByPlan[$pid][] = [
                        'feature_key' => $r['feature_key'],
                        'limits_json' => $r['limits_json'],
                    ];
                }
            }

            // 4) تطبيع الخرج مع الحفاظ على الـ aliases المطلوبة
            $out = [];
            foreach ($plans as $p) {
                $pid = (int) $p['id'];
                $price = isset($p['price']) ? (float) $p['price'] : 0.0;
                $dur   = (int) ($p['default_duration_days'] ?? 0);

                $out[] = [
                    'id'                     => $pid,
                    'name'                   => $p['name'],
                    'level'                  => (int) ($p['level'] ?? 0),
                    'price'                  => $price,   // الحقل الحقيقي
                    'amount'                 => $price,   // alias للتوافق
                    'duration_days'          => $dur,     // alias للتوافق
                    'default_duration_days'  => $dur,     // الحقل الحقيقي
                    'features'               => $featuresByPlan[$pid] ?? [],
                ];
            }

            return $this->respond([
                'status' => 'ok',
                'data'   => $out,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Plans::index error: {msg}', ['msg' => $e->getMessage()]);
            return $this->respond([
                'status'  => 'error',
                'message' => 'Failed to load plans.',
            ], 500);
        }
    }
}
