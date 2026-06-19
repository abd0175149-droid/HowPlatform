<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserSubscriptionModel extends Model
{
    protected $table         = 'user_subscriptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'plan_id', 'status', 'start_at', 'end_at',
        'source', 'voucher_code', 'created_at', 'updated_at',
    ];

    /** الاشتراك الفعّال الآن (إن وُجد) */
    public function getActiveNow(int $userId): ?array
    {
        $now = date('Y-m-d H:i:s');
        return $this->where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_at >', $now)
            ->orderBy('end_at', 'DESC')
            ->first() ?: null;
    }

    /** أقرب اشتراك مجدول قادم (إن وُجد) */
    public function getNextScheduled(int $userId): ?array
    {
        return $this->where('user_id', $userId)
            ->where('status', 'scheduled')
            ->orderBy('start_at', 'ASC')
            ->first() ?: null;
    }

    /** آخر Scheduled لنفس الخطة (للدمج) */
    public function getLastScheduledForPlan(int $userId, int $planId): ?array
    {
        return $this->where('user_id', $userId)
            ->where('status', 'scheduled')
            ->where('plan_id', $planId)
            ->orderBy('end_at', 'DESC')
            ->first() ?: null;
    }
}
