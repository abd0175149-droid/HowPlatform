<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * ServiceMessageModel
 * - يتعامل مع جدول service_messages
 * - يُستخدم لكتابة رسائل النظام/الإدارة (إن لزم).
 */
class ServiceMessageModel extends Model
{
    protected $table            = 'service_messages';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'service_request_id',
        'sender_user_id',
        'body',
        'created_at',
        'deleted_at',
    ];

    public $useTimestamps = false;

    protected $validationRules = [
        'service_request_id' => 'required|is_natural_no_zero',
        'sender_user_id'     => 'required|is_natural',
        'body'               => 'permit_empty|string',
    ];

    /** إضافة رسالة نظامية بسيطة */
    public function addSystemMessage(int $requestId, string $body, int $senderUserId = 0): bool
    {
        return (bool) $this->insert([
            'service_request_id' => $requestId,
            'sender_user_id'     => $senderUserId, // 0 = system
            'body'               => $body,
            'created_at'         => date('Y-m-d H:i:s'),
            'deleted_at'         => null,
        ]);
    }
}
