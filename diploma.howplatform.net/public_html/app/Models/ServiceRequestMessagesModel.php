<?php
// app/Models/ServiceRequestMessagesModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ServiceRequestMessagesModel extends Model
{
    protected $table         = 'service_request_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'service_request_id', 'sender_id', 'message_type', 'message',
        'is_read', 'is_deleted', 'deleted_at', 'created_at', 'updated_at'
    ];

    /** عدد الرسائل غير المقروءة لطلب ما من منظور مستخدم معيّن (لا يحتسب رسائله هو) */
    public function unreadCountForUser(int $requestId, int $viewerUserId): int
    {
        return (int) $this->asArray()
            ->where('service_request_id', $requestId)
            ->where('is_read', 0)
            ->where('sender_id <>', $viewerUserId, false)
            ->countAllResults();
    }

    /** جلب رسائل الطلب (مع ترقيم/حد) */
    public function getMessages(int $requestId, int $limit = 50, int $offset = 0): array
    {
        return $this->asArray()
            ->where('service_request_id', $requestId)
            ->orderBy('created_at', 'ASC')
            ->findAll($limit, $offset);
    }
}
