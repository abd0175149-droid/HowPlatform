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
        // وضع api: قائمة الخطط من Plans (المصدر الموثوق) مع fail-open للقراءة المحلّية.
        try {
            $client = new \App\Libraries\PlansClient();
            if ($client->plansEnabled()) {
                $res = $client->plansList('diploma');
                if (is_array($res) && ($res['ok'] ?? false) && is_array($res['plans'] ?? null)) {
                    $out = [];
                    foreach ($res['plans'] as $p) {
                        $price = (float) ($p['price'] ?? 0);
                        $dur   = (int) ($p['duration_days'] ?? 0);
                        $features = array_map(static function (array $f): array {
                            return [
                                'feature_key' => (string) ($f['feature_key'] ?? ''),
                                'limits_json' => $f['limits_json'] ?? null,
                            ];
                        }, is_array($p['features'] ?? null) ? $p['features'] : []);

                        $out[] = [
                            'id'                    => (int) ($p['id'] ?? 0),
                            'name'                  => (string) ($p['name'] ?? ''),
                            'level'                 => (int) ($p['level'] ?? 0),
                            'price'                 => $price,
                            'amount'                => $price,
                            'duration_days'         => $dur,
                            'default_duration_days' => $dur,
                            'features'              => $features,
                        ];
                    }

                    return $this->respond([
                        'status' => 'ok',
                        'data'   => $out,
                    ]);
                }
                // Plans غير متاح → تابع للقراءة المحلّية (fail-open)
            }
        } catch (Throwable $e) {
            log_message('warning', 'apiplans::index Plans failed: ' . $e->getMessage());
        }

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
