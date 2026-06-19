<?php
// app/Models/ServiceRequestFilesModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ServiceRequestFilesModel extends Model
{
    protected $table         = 'service_request_files';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'service_request_id', 'message_id', 'file_id', 'file_type', 'display_order', 'created_at'
    ];

    /** جلب ملفات مرتبطة برسالة معيّنة */
    public function getMessageFiles(int $messageId): array
    {
        return $this->asArray()
            ->where('message_id', $messageId)
            ->orderBy('display_order', 'ASC')
            ->findAll();
    }

    /** جلب مرفقات على مستوى الطلب (التي ليست مرتبطة برسالة) */
    public function getRequestAttachments(int $requestId): array
    {
        return $this->asArray()
            ->where('service_request_id', $requestId)
            ->where('message_id', null)
            ->orderBy('display_order', 'ASC')
            ->findAll();
    }
}
