<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * RewardsConsecutiveDaysModel
 * تتبع الأيام المتتالية لتسجيل الدخول
 */
class RewardsConsecutiveDaysModel extends Model
{
    protected $table            = 'rewards_consecutive_days';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField      = 'created_at';
    protected $updatedField      = 'updated_at';
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'current_streak',
        'last_login_date',
        'last_rewarded_streak',
        'created_at', // مطلوب عند useTimestamps = true
        'updated_at', // مطلوب عند useTimestamps = true و updatedField != null
    ];

    /**
     * جلب أو إنشاء سجل للمستخدم
     */
    public function getOrCreate(int $userId): array
    {
        try {
            $record = $this->where('user_id', $userId)->first();
            
            if (!$record) {
                $data = [
                    'user_id'              => $userId,
                    'current_streak'       => 0,
                    'last_rewarded_streak' => 0,
                ];
                $this->insert($data);
                return $this->where('user_id', $userId)->first() ?: [
                    'user_id'              => $userId,
                    'current_streak'       => 0,
                    'last_rewarded_streak' => 0,
                    'last_login_date'      => null,
                ];
            }
            
            return $record;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsConsecutiveDaysModel::getOrCreate error: ' . $e->getMessage());
            return [
                'user_id'              => $userId,
                'current_streak'       => 0,
                'last_rewarded_streak' => 0,
                'last_login_date'      => null,
            ];
        }
    }

    /**
     * تحديث streak المستخدم
     */
    public function updateStreak(int $userId, int $streak, string $loginDate): bool
    {
        try {
            $record = $this->getOrCreate($userId);
            if (isset($record['id'])) {
                return $this->update($record['id'], [
                    'current_streak'   => $streak,
                    'last_login_date'  => $loginDate,
                ]);
            }
            return false;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsConsecutiveDaysModel::updateStreak error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث آخر streak تم مكافأته
     */
    public function updateLastRewardedStreak(int $userId, int $streak): bool
    {
        try {
            $record = $this->getOrCreate($userId);
            if (isset($record['id'])) {
                return $this->update($record['id'], [
                    'last_rewarded_streak' => $streak,
                ]);
            }
            return false;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsConsecutiveDaysModel::updateLastRewardedStreak error: ' . $e->getMessage());
            return false;
        }
    }
}

