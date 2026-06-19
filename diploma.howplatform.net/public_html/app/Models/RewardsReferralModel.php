<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * RewardsReferralModel
 * إدارة دعوات الأصدقاء
 */
class RewardsReferralModel extends Model
{
    protected $table            = 'rewards_referrals';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField      = 'created_at';
    protected $updatedField      = null;
    protected $protectFields    = true;
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'referrer_user_id',
        'referred_user_id',
        'status',
        'referral_code',
        'completed_at',
        'rewarded_at',
        'created_at', // مطلوب عند useTimestamps = true
    ];

    /**
     * تسجيل دعوة جديدة
     */
    public function registerReferral(int $referrerUserId, int $referredUserId, string $code): ?int
    {
        // التحقق من عدم وجود دعوة مسبقة
        $existing = $this->where('referrer_user_id', $referrerUserId)
            ->where('referred_user_id', $referredUserId)
            ->first();

        if ($existing) {
            return (int)$existing['id'];
        }

        $data = [
            'referrer_user_id' => $referrerUserId,
            'referred_user_id' => $referredUserId,
            'status'          => 'pending',
            'referral_code'   => $code,
        ];

        if ($this->insert($data)) {
            return $this->getInsertID();
        }

        return null;
    }

    /**
     * جلب دعوة حسب المستخدم المدعو
     */
    public function getReferralByReferredUser(int $referredUserId): ?array
    {
        return $this->where('referred_user_id', $referredUserId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * تحديث حالة الدعوة إلى completed
     */
    public function markAsCompleted(int $referralId): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->update($referralId, [
            'status'       => 'completed',
            'completed_at' => $now,
        ]);
    }

    /**
     * تحديث حالة الدعوة إلى rewarded
     */
    public function markAsRewarded(int $referralId): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->update($referralId, [
            'status'      => 'rewarded',
            'rewarded_at' => $now,
        ]);
    }

    /**
     * جلب جميع دعوات المستخدم
     */
    public function getUserReferrals(int $referrerUserId): array
    {
        return $this->where('referrer_user_id', $referrerUserId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * التحقق من صحة كود الدعوة وجلب صاحب الكود
     * 
     * ملاحظة: الكود يكون صالحاً إذا كان صاحبه موجوداً في diploma_users
     * يمكن أن يكون الكود موجوداً في سجل أو يمكن استخراجه من الكود نفسه
     */
    public function validateReferralCode(string $code): ?int
    {
        // البحث عن أي سجل يستخدم هذا الكود
        $referral = $this->where('referral_code', $code)
            ->first();

        if ($referral) {
            $referrerUserId = (int)$referral['referrer_user_id'];
            
            // التحقق من وجود المستخدم في diploma_users
            $db = \Config\Database::connect();
            $userExists = $db->table('diploma_users')
                ->where('user_id', $referrerUserId)
                ->exists();
            
            return $userExists ? $referrerUserId : null;
        }

        // إذا لم نجد سجل، نحاول البحث في جميع المستخدمين
        // الكود ثابت لكل مستخدم (REF + hash من user_id)
        // نبحث في جميع المستخدمين في diploma_users
        $db = \Config\Database::connect();
        $users = $db->table('diploma_users')
            ->select('user_id')
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            $testUserId = (int)$user['user_id'];
            // استخدام نفس منطق generateReferralCode من ReferralSystem
            $hash = md5('referral_' . $testUserId . '_howplatform');
            $testCode = 'REF' . strtoupper(substr($hash, 0, 8));
            if ($testCode === $code) {
                return $testUserId;
            }
        }

        return null;
    }

    /**
     * إنشاء كود دعوة فريد للمستخدم
     */
    public function generateReferralCodeForUser(int $userId): string
    {
        // إنشاء كود فريد بناءً على user_id
        $code = 'REF' . strtoupper(substr(md5($userId . time() . uniqid()), 0, 8));
        
        // التحقق من عدم التكرار (نادر جداً)
        while ($this->where('referral_code', $code)->first()) {
            $code = 'REF' . strtoupper(substr(md5($userId . time() . uniqid()), 0, 8));
        }
        
        return $code;
    }
}

