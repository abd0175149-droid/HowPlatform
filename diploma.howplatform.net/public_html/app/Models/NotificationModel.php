<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table      = 'notifications';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'user_id',
        'notification_type_id',
        'title',
        'message',
        'is_read',
        'redirect_url',
        'created_at'
    ];

    protected $useTimestamps = false;

    // إن أردت تفعيل الـ timestamps لاحقًا
    // protected $createdField = 'created_at';
}
