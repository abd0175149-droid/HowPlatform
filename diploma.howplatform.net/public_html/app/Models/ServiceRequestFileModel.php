<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ServiceRequestFileModel extends Model
{
    protected $table            = 'service_request_files';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /**
     * - الربط ممكن على مستوى الطلب (service_request_id) أو الرسالة (message_id).
     * - هذا الجدول لا يحوي created_at/updated_at.
     */
    protected $allowedFields = [
        'service_request_id', 'message_id', 'file_id', 'file_type', 'display_order',
    ];

    protected $useTimestamps = false;

    /** القيم المسموحة لنوع الملف داخل سجل الربط */
    public const FILE_TYPES = ['attachment', 'voice', 'image', 'document'];

    protected $validationRules = [
        'service_request_id' => 'permit_empty|is_natural_no_zero',
        'message_id'         => 'permit_empty|is_natural_no_zero',
        'file_id'            => 'required|is_natural_no_zero',
        'file_type'          => 'permit_empty|in_list[attachment,voice,image,document]',
        'display_order'      => 'permit_empty|integer',
    ];

    /* =================== ربط الملفات =================== */

    /**
     * ربط ملف برسالة. يملأ service_request_id تلقائيًا.
     */
    public function attachFileToMessage(int $messageId, int $fileId, string $fileType = 'attachment', int $displayOrder = 0)
    {
        $messageId = (int) $messageId;
        $fileId    = (int) $fileId;

        // جلب request_id من الرسالة (service_messages)
        $reqId = $this->db->table('service_messages')
            ->select('service_request_id')
            ->where('id', $messageId)
            ->get()
            ->getRow('service_request_id');

        if (!in_array($fileType, self::FILE_TYPES, true)) {
            $fileType = 'attachment';
        }

        return $this->insert([
            'service_request_id' => $reqId ? (int) $reqId : null,
            'message_id'         => $messageId,
            'file_id'            => $fileId,
            'file_type'          => $fileType,
            'display_order'      => $displayOrder,
        ]);
    }

    public function attachFilesToMessageBulk(int $messageId, array $fileIds, string $fileType = 'attachment'): int
    {
        $count   = 0;
        $orderIx = 0;

        $this->db->transStart();

        foreach ($fileIds as $fid) {
            $fid = (int) $fid;
            if ($fid <= 0) {
                continue;
            }
            if ($this->existsLink($messageId, $fid)) {
                continue;
            }
            $ok = $this->attachFileToMessage($messageId, $fid, $fileType, $orderIx++);
            if ($ok) {
                $count++;
            }
        }

        $this->db->transComplete();
        return $this->db->transStatus() ? $count : 0;
    }

    public function attachFileToRequest(int $requestId, int $fileId, string $fileType = 'attachment', int $displayOrder = 0)
    {
        if (!in_array($fileType, self::FILE_TYPES, true)) {
            $fileType = 'attachment';
        }

        return $this->insert([
            'service_request_id' => (int) $requestId,
            'message_id'         => null,
            'file_id'            => (int) $fileId,
            'file_type'          => $fileType,
            'display_order'      => (int) $displayOrder,
        ]);
    }

    public function existsLink(int $messageId, int $fileId): bool
    {
        return $this->where('message_id', $messageId)
                    ->where('file_id', $fileId)
                    ->countAllResults() > 0;
    }

    /* =================== جلب الملفات =================== */

    /** ملفات رسالة واحدة — يجلب بيانات files.* مع بيانات الربط */
    public function getFilesForMessage(int $messageId): array
    {
        return $this->db->table($this->table . ' srf')
            ->select('
                srf.id               AS link_id,
                srf.message_id,
                srf.file_id,
                srf.file_type,
                srf.display_order,
                f.id                 AS id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes
            ')
            ->join('files f', 'f.id = srf.file_id', 'inner')
            ->where('srf.message_id', $messageId)
            ->orderBy('srf.display_order', 'ASC')
            ->orderBy('srf.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** مرفقات أولية على مستوى الطلب فقط (message_id IS NULL) */
    public function getFilesForRequest(int $requestId): array
    {
        return $this->db->table($this->table . ' srf')
            ->select('
                srf.id               AS link_id,
                srf.service_request_id,
                srf.file_id,
                srf.file_type,
                srf.display_order,
                f.id                 AS id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes
            ')
            ->join('files f', 'f.id = srf.file_id', 'inner')
            ->where('srf.service_request_id', $requestId)
            ->where('srf.message_id IS NULL', null, false)
            ->orderBy('srf.display_order', 'ASC')
            ->orderBy('srf.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** تجميع ملفات الرسائل لكل طلب */
    public function getFilesGroupedByMessage(int $requestId): array
    {
        $rows = $this->db->table($this->table . ' srf')
            ->select('
                srf.message_id,
                srf.file_type,
                srf.display_order,
                f.id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes
            ')
            ->join('service_messages sm', 'sm.id = srf.message_id', 'inner')
            ->join('files f', 'f.id = srf.file_id', 'inner')
            ->where('sm.service_request_id', $requestId)
            ->orderBy('srf.message_id', 'ASC')
            ->orderBy('srf.display_order', 'ASC')
            ->orderBy('srf.id', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $r) {
            $mid = (int) $r['message_id'];
            unset($r['message_id']);
            $grouped[$mid][] = $r;
        }
        return $grouped;
    }

    /* =================== صلاحيات الوصول للتحميل =================== */

    /** صلاحيات الطالب: صاحب الطلب أو رافع الملف */
    public function getFileWithPermissions(int $fileId, int $userId): ?array
    {
        $row = $this->db->table('files f')
            ->select('
                f.id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes,
                f.uploader_id
            ')
            ->join($this->table . ' srf', 'srf.file_id = f.id', 'left')
            ->join('service_messages sm', 'sm.id = srf.message_id', 'left')
            ->join('service_requests sr', '(sr.id = sm.service_request_id OR sr.id = srf.service_request_id)', 'left', false)
            ->where('f.id', $fileId)
            ->groupStart()
                ->where('f.uploader_id', $userId)
                ->orWhere('sr.user_id',    $userId)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /** صلاحيات الأدمن: المكلّف أو رافع الملف أو صاحب الطلب */
    public function getFileWithPermissionsForAdmin(int $fileId, int $userId): ?array
    {
        $row = $this->db->table('files f')
            ->select('
                f.id,
                f.file_name,
                f.file_path,
                f.mime_type,
                f.file_size_bytes,
                f.uploader_id
            ')
            ->join($this->table . ' srf', 'srf.file_id = f.id', 'left')
            ->join('service_messages sm', 'sm.id = srf.message_id', 'left')
            ->join('service_requests sr', '(sr.id = sm.service_request_id OR sr.id = srf.service_request_id)', 'left', false)
            ->where('f.id', $fileId)
            ->groupStart()
                ->where('f.uploader_id', $userId)
                ->orWhere('sr.assigned_to', $userId)
                ->orWhere('sr.user_id',     $userId)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /* =================== شؤون إدارية =================== */

    public function removeFileFromMessage(int $messageId, int $fileId)
    {
        return $this->where('message_id', $messageId)
                    ->where('file_id', $fileId)
                    ->delete();
    }

    public function removeFileFromRequest(int $requestId, int $fileId)
    {
        return $this->where('service_request_id', $requestId)
                    ->where('file_id', $fileId)
                    ->where('message_id IS NULL', null, false)
                    ->delete();
    }

    public function getFileCountForMessage(int $messageId): int
    {
        return (int) $this->where('message_id', $messageId)->countAllResults();
    }

    public function getFileCountForRequest(int $requestId): int
    {
        return (int) $this->where('service_request_id', $requestId)
                          ->where('message_id IS NULL', null, false)
                          ->countAllResults();
    }
}
