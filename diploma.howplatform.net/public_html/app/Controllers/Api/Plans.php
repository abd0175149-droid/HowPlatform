<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\PlanModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Plans extends ResourceController
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
                                'feature_key'  => (string) ($f['feature_key'] ?? ''),
                                'feature_text' => (string) ($f['feature_text'] ?? ($f['feature_key'] ?? '')), // اسم بشري للعرض
                                'value'        => $f['value'] ?? null,
                                'limits_json'  => $f['limits_json'] ?? null,
                            ];
                        }, is_array($p['features'] ?? null) ? $p['features'] : []);

                        $out[] = [
                            'id'                    => (int) ($p['id'] ?? 0),
                            'name'                  => (string) ($p['name'] ?? ''),
                            'level'                 => (int) ($p['level'] ?? 0),
                            'price'                 => $price,   // الحقل الحقيقي
                            'amount'                => $price,   // alias للتوافق
                            'duration_days'         => $dur,     // alias للتوافق
                            'default_duration_days' => $dur,     // الحقل الحقيقي
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
        } catch (\Throwable $e) {
            log_message('warning', 'Plans::index Plans failed: ' . $e->getMessage());
        }

        try {
            $model = new PlanModel();
            $plans = $model->getActivePlansWithFeatures();

            return $this->respond([
                'status' => 'ok',
                'data'   => $plans,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Plans::index error: {msg}', ['msg' => $e->getMessage()]);
            return $this->respond([
                'status'  => 'error',
                'message' => 'Failed to load plans.',
            ], 500);
        }
    }
}
