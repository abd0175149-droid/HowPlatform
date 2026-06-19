<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * ServiceMessageReadModel
 * - يتعامل مع جدول service_message_reads
 * - يُستخدم لوسم الرسائل كمقروءة لكل مستخدم (اختياري في صفحة الجدول).
 */
class ServiceMessageReadModel extends Model
{
    protected $table         = 'service_message_reads';
    protected $primaryKey    = null;          // مركّب (message_id, reader_user_id)
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $protectFields = true;
    protected $useAutoIncrement = false;

    protected $allowedFields = [
        'message_id',
        'reader_user_id',
        'read_at',
    ];

    protected $validationRules = [
        'message_id'     => 'required|is_natural_no_zero',
        'reader_user_id' => 'required|is_natural_no_zero',
        'read_at'        => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];
}
