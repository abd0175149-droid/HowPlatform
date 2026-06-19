<?php
declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table         = 'plans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'name', 'level', 'default_duration_days', 'price', 'is_active', 'created_at', 'updated_at',
    ];

    /**
     * يرجع الخطط الفعّالة + مزاياها وفق الأعمدة الفعلية:
     * - price, default_duration_days
     * ويضيف alias للتوافق:
     * - amount = price
     * - duration_days = default_duration_days
     */
    public function getActivePlansWithFeatures(): array
    {
        // جلب الخطط الفعّالة مرتّبة حسب level ثم price
        $plans = $this->select('id, name, level, default_duration_days, price, is_active')
                      ->where('is_active', 1)
                      ->orderBy('level', 'ASC')
                      ->orderBy('price', 'ASC')
                      ->findAll();

        if (!$plans) {
            return [];
        }

        $ids = array_map(static fn($p) => (int)$p['id'], $plans);

        // جلب المزايا
        $rows = $this->db->table('plan_features')
            ->select('plan_id, feature_key, limits_json')
            ->whereIn('plan_id', $ids)
            ->orderBy('id', 'ASC')
            ->get();

        $byPlan = [];
        if ($rows !== false) {
            foreach ($rows->getResultArray() as $r) {
                $byPlan[(int)$r['plan_id']][] = [
                    'feature_key' => $r['feature_key'],
                    'limits_json' => $r['limits_json'],
                ];
            }
        }

        // تطبيع خرج متوافق مع الواجهة
        $out = [];
        foreach ($plans as $p) {
            $pid   = (int)$p['id'];
            $price = isset($p['price']) ? (float)$p['price'] : 0.0;
            $dur   = (int)($p['default_duration_days'] ?? 0);

            $out[] = [
                'id'             => $pid,
                'name'           => $p['name'],
                'level'          => (int)$p['level'],
                'price'          => $price,   // الحقل الحقيقي
                'amount'         => $price,   // alias للتوافق
                'duration_days'  => $dur,     // alias للتوافق
                'default_duration_days' => $dur, // الحقل الحقيقي
                'features'       => $byPlan[$pid] ?? [],
            ];
        }

        return $out;
    }
}
