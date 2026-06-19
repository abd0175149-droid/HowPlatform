<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * RewardsTransactionModel
 * إدارة معاملات النقاط (سجل جميع العمليات)
 */
class RewardsTransactionModel extends Model
{
    protected $table            = 'rewards_transactions';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField      = 'created_at';
    protected $updatedField      = null;
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'points',
        'reward_type',
        'reward_type_id',
        'description',
        'created_at', // مطلوب عند useTimestamps = true
    ];

    /**
     * جلب معاملات المستخدم مع معلومات المهام
     */
    public function getUserTransactions(int $userId, int $limit = 50, int $offset = 0): array
    {
        $db = \Config\Database::connect();
        
        // استخدام select من جدول rewards_transactions مع LEFT JOIN للمهام
        $builder = $db->table($this->table . ' AS rt')
            ->select('rt.*, t.id AS task_id, t.title AS task_title')
            ->join('tasks AS t', 't.id = rt.reward_type_id AND rt.reward_type = "ai_tool_usage"', 'left')
            ->where('rt.user_id', $userId)
            ->orderBy('rt.created_at', 'DESC')
            ->limit($limit, $offset);
        
        $result = $builder->get();
        return $result ? $result->getResultArray() : [];
    }
    
    /**
     * جلب عدد المعاملات الإجمالي للمستخدم
     */
    public function getUserTransactionsCount(int $userId): int
    {
        return $this->where('user_id', $userId)->countAllResults();
    }

    /**
     * إضافة معاملة جديدة
     *
     * @param string|null $createdAt وقت العملية (مثلاً بتوقيت Asia/Amman) Y-m-d H:i:s؛ إن غاب يُستخدم توقيت السيرفر
     */
    public function addTransaction(
        int $userId,
        int $points,
        string $rewardType,
        ?int $rewardTypeId = null,
        ?string $description = null,
        ?string $createdAt = null
    ): ?int {
        try {
            $db = \Config\Database::connect();
            $insertData = [
                'user_id'       => $userId,
                'points'        => $points,
                'reward_type'   => $rewardType,
                'reward_type_id' => $rewardTypeId,
                'description'   => $description,
                'created_at'    => $createdAt ?? date('Y-m-d H:i:s'),
            ];
            
            $insertResult = $db->table($this->table)->insert($insertData);
            
            if ($insertResult) {
                return $db->insertID();
            }

            return null;
        } catch (\Throwable $e) {
            log_message('debug', 'RewardsTransactionModel::addTransaction error: ' . $e->getMessage());
            return null;
        }
    }
}

