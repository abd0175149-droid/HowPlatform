<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * RewardsUserModel
 * إدارة نقاط المستخدمين في نظام المكافآت
 */
class RewardsUserModel extends Model
{
    protected $table            = 'rewards_users';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField      = 'created_at';
    protected $updatedField      = 'updated_at';
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'total_points',
        'created_at', // مطلوب عند useTimestamps = true
        'updated_at', // مطلوب عند useTimestamps = true و updatedField != null
    ];

    /**
     * جلب أو إنشاء سجل نقاط للمستخدم
     */
    public function getOrCreate(int $userId): array
    {
        try {
            $user = $this->where('user_id', $userId)->first();
            
            if (!$user) {
                $data = [
                    'user_id'      => $userId,
                    'total_points' => 0,
                ];
                $this->insert($data);
                return $this->where('user_id', $userId)->first() ?: [
                    'user_id'      => $userId,
                    'total_points' => 0,
                ];
            }
            
            return $user;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsUserModel::getOrCreate error: ' . $e->getMessage());
            return [
                'user_id'      => $userId,
                'total_points' => 0,
            ];
        }
    }

    /**
     * تحديث نقاط المستخدم
     */
    public function updatePoints(int $userId, int $points): bool
    {
        try {
            $user = $this->getOrCreate($userId);
            if (isset($user['id'])) {
                return $this->update($user['id'], [
                    'total_points' => $points,
                ]);
            }
            return false;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsUserModel::updatePoints error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إضافة نقاط للمستخدم
     */
    public function addPoints(int $userId, int $points): bool
    {
        try {
            $user = $this->getOrCreate($userId);
            $newTotal = (int)($user['total_points'] ?? 0) + $points;
            return $this->updatePoints($userId, $newTotal);
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsUserModel::addPoints error: ' . $e->getMessage());
            return false;
        }
    }
}

