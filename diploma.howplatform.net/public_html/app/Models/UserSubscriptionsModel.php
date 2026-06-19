<?php
// app/Models/UserSubscriptionsModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserSubscriptionsModel extends Model
{
    protected $table         = 'user_subscriptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'plan_id', 'status', 'start_at', 'end_at', 'source', 'voucher_code',
        'created_at', 'updated_at'
    ];

    /**
     * إرجاع أعلى مستوى خطة فعّالة الآن لهذا المستخدم (0 إن لا يوجد خطة فعّالة).
     * التعريف: status='active' AND start_at <= NOW() AND end_at > NOW()
     */
    public function activeMaxLevel(int $userId): int
    {
        $db = $this->db;
        $builder = $db->table($this->table . ' us');
        $builder->select('COALESCE(MAX(p.level), 0) AS plan_level', false)
            ->join('plans p', 'p.id = us.plan_id', 'inner')
            ->where('us.user_id', $userId)
            ->where('us.status', 'active')
            ->where('us.start_at <=', date('Y-m-d H:i:s'))
            ->where('us.end_at >', date('Y-m-d H:i:s'));

        $row = $builder->get()->getRowArray();

        return (int) ($row['plan_level'] ?? 0);
    }
}
