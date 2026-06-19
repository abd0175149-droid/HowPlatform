<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * RewardsDailyLoginModel
 * سجل تسجيلات الدخول اليومية
 */
class RewardsDailyLoginModel extends Model
{
    protected $table            = 'rewards_daily_logins';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField      = 'created_at';
    protected $updatedField      = null;
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'login_date',
        'created_at', // مطلوب عند useTimestamps = true
    ];

    /**
     * تسجيل دخول يومي (إذا لم يكن مسجل مسبقاً)
     *
     * @param int $userId
     * @param string $date تاريخ الدخول Y-m-d
     * @param string|null $loginDatetime وقت الدخول الفعلي (مثلاً بتوقيت Asia/Amman) Y-m-d H:i:s؛ إن غاب يُستخدم توقيت السيرفر
     */
    public function recordLogin(int $userId, string $date, ?string $loginDatetime = null): bool
    {
        try {
            $existing = $this->where('user_id', $userId)
                ->where('login_date', $date)
                ->first();

            if ($existing) {
                return true;
            }

            $db = \Config\Database::connect();
            $insertResult = $db->table($this->table)->insert([
                'user_id'    => $userId,
                'login_date' => $date,
                'created_at' => $loginDatetime ?? date('Y-m-d H:i:s'),
            ]);

            $insertId = $insertResult ? $db->insertID() : false;

            if ($insertId === false) {
                $errors = $this->errors();
                log_message('error', 'RewardsDailyLoginModel::recordLogin insert failed for user ' . $userId . ' on ' . $date . '. Errors: ' . json_encode($errors));
                return false;
            }

            log_message('debug', "RewardsDailyLoginModel: Successfully recorded login for user {$userId} on {$date} (ID: {$insertId})");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'RewardsDailyLoginModel::recordLogin exception for user ' . $userId . ' on ' . $date . ': ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * التحقق من تسجيل الدخول في تاريخ معين
     */
    public function hasLoggedIn(int $userId, string $date): bool
    {
        return $this->where('user_id', $userId)
            ->where('login_date', $date)
            ->first() !== null;
    }

    /**
     * جلب جميع تسجيلات الدخول للمستخدم
     */
    public function getUserLogins(int $userId, int $limit = 30): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('login_date', 'DESC')
            ->findAll($limit);
    }
}
