<?php
namespace App\Models;

use CodeIgniter\Model;

class UserDeviceModel extends Model
{
    protected $table         = 'user_devices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id','device_fingerprint','device_label','ip_address','user_agent',
        'is_active','last_used_at','created_at','updated_at'
    ];

    /** عدّ الأجهزة المفعّلة للمستخدم */
    public function countActiveByUser(int $userId): int
    {
        return $this->where(['user_id' => $userId, 'is_active' => 1])->countAllResults();
    }

    /** إيجاد جهاز ببصمة محددة */
    public function findByFingerprint(int $userId, string $fp): ?array
    {
        return $this->where(['user_id' => $userId, 'device_fingerprint' => $fp])->first();
    }
}
