<?php namespace App\Models;

use CodeIgniter\Model;

class PlanTypeFeatureModel extends Model
{
    protected $table         = 'plan_type_features';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['plan_type_id','feature_text','display_order'];

    /**
     * يجلب مزايا عدة خطط دفعة واحدة ويرجّعها مجمّعة حسب plan_type_id
     */
    public function getByPlanIds(array $planIds): array
    {
        if (empty($planIds)) return [];

        $rows = $this->whereIn('plan_type_id', $planIds)
                     ->orderBy('plan_type_id', 'ASC')
                     ->orderBy('display_order', 'ASC')
                     ->findAll();

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['plan_type_id']][] = [
                'text'          => $r['feature_text'],
                'display_order' => (int)$r['display_order'],
            ];
        }
        return $grouped;
    }
}
