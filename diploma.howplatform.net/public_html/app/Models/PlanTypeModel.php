<?php namespace App\Models;

use CodeIgniter\Model;

class PlanTypeModel extends Model
{
    protected $table         = 'plan_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['plan_type_name','amount','duration_days','is_active','sort_order'];

    public function getActiveOrdered(): array
    {
        return $this->where('is_active', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}
