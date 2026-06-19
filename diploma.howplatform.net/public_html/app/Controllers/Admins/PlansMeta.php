<?php
declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\PlanModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class PlansMeta extends BaseController
{
    public function page(): string
    {
        return view('admins/pages/plans');
    }

    public function index(): ResponseInterface
    {
        $showInactive = (int) $this->request->getGet('show_inactive') === 1;
        $search       = trim((string) $this->request->getGet('q'));

        $m = new PlanModel();
        $builder = $m->select('id, name, level, default_duration_days, price, is_active');

        if (!$showInactive) $builder->where('is_active', 1);
        if ($search !== '') $builder->like('name', $search, 'both');

        $plans = $builder->orderBy('level', 'ASC')->orderBy('price', 'ASC')->findAll();
        if (!$plans) {
            return $this->response->setJSON(['status' => 'ok', 'data' => ['plans' => [], 'features' => []]]);
        }

        $ids = array_map(static fn($p) => (int) $p['id'], $plans);
        $db  = Database::connect();
        $rows = $db->table('plan_features')
            ->select('id, plan_id, feature_key, limits_json')
            ->whereIn('plan_id', $ids)
            ->orderBy('id', 'ASC')
            ->get();

        $byPlan = [];
        foreach ($rows->getResultArray() as $r) {
            $pid = (int) $r['plan_id'];
            $byPlan[$pid][] = [
                'id'          => (int) $r['id'],
                'feature_key' => $r['feature_key'],
                'limits_json' => $r['limits_json'],
            ];
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => [
                'plans'    => $plans,
                'features' => $byPlan,
            ],
        ]);
    }

    /** ✅ إنشاء خطة + (اختياري) ميزات متعددة */
    public function store(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $data = [
            'name'                  => trim((string) ($json['name'] ?? '')),
            'level'                 => (int) ($json['level'] ?? 1),
            'default_duration_days' => (int) ($json['default_duration_days'] ?? 30),
            'price'                 => array_key_exists('price', $json) && $json['price'] !== '' ? (float) $json['price'] : null,
            'is_active'             => (int) ($json['is_active'] ?? 1),
        ];
        if ($data['name'] === '' || $data['level'] < 1 || $data['default_duration_days'] < 1) {
            return $this->fail('بيانات غير صالحة', 422);
        }

        $features = $json['features'] ?? null; // مصفوفة [{feature_key, limits_json}, ...] أو null

        $db = Database::connect();
        $db->transStart();

        $m  = new PlanModel();
        $id = $m->insert($data, true);
        if (!$id) {
            $db->transRollback();
            return $this->fail('تعذّر إنشاء الخطة');
        }

        if (is_array($features) && !empty($features)) {
            $ins = $db->table('plan_features');
            foreach ($features as $row) {
                $fk = trim((string) ($row['feature_key'] ?? ''));
                if ($fk === '') continue; // نتجاهل الصفوف الفارغة

                $limits = $row['limits_json'] ?? null;
                if ($limits !== null && !is_array($limits) && !is_object($limits)) {
                    if (is_string($limits)) {
                        $decoded = json_decode($limits, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $db->transRollback();
                            return $this->fail('limits_json ليس JSON صالحًا', 422);
                        }
                        $limits = $decoded;
                    } else {
                        $db->transRollback();
                        return $this->fail('limits_json ليس JSON صالحًا', 422);
                    }
                }

                try {
                    $ins->insert([
                        'plan_id'     => (int) $id,
                        'feature_key' => $fk,
                        'limits_json' => $limits ? json_encode($limits, JSON_UNESCAPED_UNICODE) : null,
                    ]);
                } catch (\Throwable $e) {
                    $db->transRollback();
                    return $this->fail('تعذّر إضافة إحدى الميزات (تفرد/JSON)', 422);
                }
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) return $this->fail('تعذّر إتمام العملية');

        return $this->response->setJSON(['status' => 'ok', 'id' => (int) $id]);
    }

    public function update(int $id): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $data = array_filter([
            'name'                  => isset($json['name']) ? trim((string) $json['name']) : null,
            'level'                 => isset($json['level']) ? (int) $json['level'] : null,
            'default_duration_days' => isset($json['default_duration_days']) ? (int) $json['default_duration_days'] : null,
            'price'                 => array_key_exists('price', $json) ? ($json['price'] !== '' ? (float) $json['price'] : null) : null,
            'is_active'             => isset($json['is_active']) ? (int) $json['is_active'] : null,
        ], static fn($v) => $v !== null);

        if (empty($data)) return $this->fail('لا توجد بيانات لتحديثها', 422);

        $m = new PlanModel();
        if (!$m->update($id, $data)) return $this->fail('تعذّر تحديث الخطة');

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function delete(int $id): ResponseInterface
    {
        $m = new PlanModel();
        if (!$m->delete($id)) return $this->fail('تعذّر حذف الخطة');
        return $this->response->setJSON(['status' => 'ok']);
    }

    public function addFeature(int $planId): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $db   = Database::connect();

        // يدعم: كائن مفرد {feature_key, limits_json} أو مصفوفة منهم
        $rows = isset($json[0]) ? $json : [$json];

        $ins = $db->table('plan_features');
        $db->transStart();

        foreach ($rows as $row) {
            $fk = trim((string) ($row['feature_key'] ?? ''));
            if ($fk === '') continue;

            $limits = $row['limits_json'] ?? null;
            if ($limits !== null && !is_array($limits) && !is_object($limits)) {
                if (is_string($limits)) {
                    $decoded = json_decode($limits, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $db->transRollback();
                        return $this->fail('limits_json ليس JSON صالحًا', 422);
                    }
                    $limits = $decoded;
                } else {
                    $db->transRollback();
                    return $this->fail('limits_json ليس JSON صالحًا', 422);
                }
            }

            try {
                $ins->insert([
                    'plan_id'     => $planId,
                    'feature_key' => $fk,
                    'limits_json' => $limits ? json_encode($limits, JSON_UNESCAPED_UNICODE) : null,
                ]);
            } catch (\Throwable $e) {
                $db->transRollback();
                return $this->fail('تعذّر إضافة ميزة (تفرد/JSON)', 422);
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) return $this->fail('فشل إضافة الميزات');

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function updateFeature(int $featureId): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $data = [];

        if (isset($json['feature_key'])) {
            $fk = trim((string) $json['feature_key']);
            if ($fk === '') return $this->fail('feature_key غير صالح', 422);
            $data['feature_key'] = $fk;
        }
        if (array_key_exists('limits_json', $json)) {
            $limits = $json['limits_json'];
            if ($limits !== null && !is_array($limits) && !is_object($limits)) {
                if (is_string($limits)) {
                    $decoded = json_decode($limits, true);
                    if (json_last_error() !== JSON_ERROR_NONE) return $this->fail('limits_json ليس JSON صالحًا', 422);
                    $limits = $decoded;
                } else {
                    return $this->fail('limits_json ليس JSON صالحًا', 422);
                }
            }
            $data['limits_json'] = $limits ? json_encode($limits, JSON_UNESCAPED_UNICODE) : null;
        }

        if (empty($data)) return $this->fail('لا توجد بيانات لتحديثها', 422);

        $db = Database::connect();
        $ok = $db->table('plan_features')->where('id', $featureId)->update($data);
        if (!$ok) return $this->fail('تعذّر تحديث الميزة');

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function deleteFeature(int $featureId): ResponseInterface
    {
        $db = Database::connect();
        $ok = $db->table('plan_features')->where('id', $featureId)->delete();
        if (!$ok) return $this->fail('تعذّر حذف الميزة');

        return $this->response->setJSON(['status' => 'ok']);
    }

    private function fail(string $msg, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['status' => 'fail', 'message' => $msg]);
    }
}
