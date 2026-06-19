<?php
namespace App\Models;

use CodeIgniter\Model;

class ServiceRequestMessageModel extends Model
{
    protected $table            = 'service_request_messages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    // السماح بهذه الحقول (مع created_at/updated_at بسبب useTimestamps)
    protected $allowedFields = [
        'service_request_id', 'sender_id', 'message_type', 'message',
        'is_read', 'is_deleted', 'deleted_at',
        'created_at', 'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'service_request_id' => 'required|is_natural_no_zero',
        'sender_id'          => 'required|is_natural_no_zero',
        'message_type'       => 'permit_empty|in_list[text,voice,file]',
        'message'            => 'permit_empty|string',
        'is_read'            => 'permit_empty|in_list[0,1]',
        'is_deleted'         => 'permit_empty|in_list[0,1]',
        'deleted_at'         => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];

    /* ================== داخلي ================== */

    /** SELECT الأساسي مع is_self */
    private function baseSelect(int $viewerId): string
    {
        $vid = (int) $viewerId;
        return "
            m.id,
            m.service_request_id,
            m.sender_id,
            m.message_type,
            m.message,
            m.is_read,
            m.is_deleted,
            m.created_at,
            m.updated_at,
            CASE WHEN m.sender_id = {$vid} THEN 1 ELSE 0 END AS is_self
        ";
    }

    /**
     * يطبّق شرط "غير محذوف" مع مراعاة القيم NULL
     */
    private function applyNotDeletedFilter($builder, string $alias = 'm')
    {
        $prefix = $alias !== '' ? "{$alias}." : '';

        $builder->groupStart()
            ->where("{$prefix}is_deleted", 0)
            ->orWhere("{$prefix}is_deleted IS NULL", null, false)
        ->groupEnd();

        $builder->groupStart()
            ->where("{$prefix}deleted_at IS NULL", null, false)
            ->orWhere("{$prefix}deleted_at", '')
            ->orWhere("{$prefix}deleted_at", '0000-00-00 00:00:00')
        ->groupEnd();

        return $builder;
    }

    /**
     * يجلب المرفقات لعدة رسائل دفعة واحدة ويرجعها كماب: message_id => [files...]
     */
    protected function getFilesForMessageIds(array $messageIds): array
    {
        if (empty($messageIds)) return [];

        $rows = $this->db->table('service_request_files srf')
            ->select('
                srf.message_id,
                srf.file_type,
                srf.display_order,
                f.id               AS file_id,
                f.file_name,
                f.mime_type,
                f.file_size_bytes
            ')
            ->join('files f', 'f.id = srf.file_id', 'left')
            ->whereIn('srf.message_id', array_map('intval', $messageIds))
            ->orderBy('srf.display_order', 'ASC')
            ->orderBy('srf.id', 'ASC')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $msgId = (int) $r['message_id'];
            unset($r['message_id']);
            $r['download_url'] = '/admins/service-requests-new/file/' . (int) $r['file_id']; // متوافق مع الراوتر الجديد
            $r['id']           = (int) $r['file_id']; // توافقًا مع واجهات تتوقع id
            $map[$msgId][]     = $r;
        }
        return $map;
    }

    /**
     * يحقن مصفوفة files داخل كل رسالة + يضبط اختصارات لـ voice/file
     */
    protected function hydrateFiles(array $messages): array
    {
        if (empty($messages) || !is_array($messages)) {
            log_message('debug', 'hydrateFiles: Empty or invalid messages array');
            return [];
        }

        $ids = [];
        foreach ($messages as $m) {
            if (isset($m['id']) && !empty($m['id'])) {
                $ids[] = (int) $m['id'];
            }
        }
        
        $filesMap = !empty($ids) ? $this->getFilesForMessageIds($ids) : [];

        foreach ($messages as &$m) {
            if (!is_array($m) || !isset($m['id'])) {
                log_message('warning', 'hydrateFiles: Invalid message structure: ' . json_encode($m));
                continue;
            }
            
            $mid        = (int) $m['id'];
            $m['files'] = $filesMap[$mid] ?? [];

            $type = $m['message_type'] ?? 'text';

            if ($type === 'voice' && !empty($m['files'])) {
                foreach ($m['files'] as $f) {
                    $isVoiceType = (isset($f['file_type']) && $f['file_type'] === 'voice');
                    $isAudioMime = (isset($f['mime_type']) && strpos((string)$f['mime_type'], 'audio/') === 0);
                    if ($isVoiceType || $isAudioMime) {
                        $m['download_url'] = $f['download_url'] ?? '';
                        $m['file_id']      = $f['file_id'] ?? null;
                        break;
                    }
                }
                if (empty($m['download_url']) && !empty($m['files'][0]['download_url'])) {
                    $m['download_url'] = $m['files'][0]['download_url'];
                    $m['file_id']      = $m['files'][0]['file_id'] ?? null;
                }
            }

            if ($type === 'file' && !empty($m['files'])) {
                $f = $m['files'][0];
                $m['file_id']      = $f['file_id'] ?? null;
                $m['file_name']    = $f['file_name'] ?? null;
                $m['download_url'] = $f['download_url'] ?? null;
            }
            
            // التأكد من وجود الحقول الأساسية
            if (!isset($m['sender_id'])) {
                $m['sender_id'] = 0;
            }
            if (!isset($m['created_at'])) {
                $m['created_at'] = date('Y-m-d H:i:s');
            }
        }
        unset($m);

        return $messages;
    }

    /* =================== دوال عامة =================== */

    public function getMessagesForRequest($requestId, $currentUserId = null, $limit = null, $afterId = null)
    {
        $requestId = (int) $requestId;
        $viewerId  = (int) ($currentUserId ?? (session('user_id') ?? 0));

        if ($requestId <= 0) {
            log_message('error', 'getMessagesForRequest: Invalid requestId: ' . $requestId);
            return [];
        }

        $builder = $this->db->table($this->table . ' m')
            ->select($this->baseSelect($viewerId), false)
            ->where('m.service_request_id', $requestId);

        $this->applyNotDeletedFilter($builder, 'm');

        if ($afterId !== null) {
            $builder->where('m.id >', (int) $afterId);
        }
        if ($limit !== null) {
            $builder->limit((int) $limit);
        }

        $rows = $builder->orderBy('m.created_at', 'ASC')
                        ->orderBy('m.id', 'ASC')
                        ->get()
                        ->getResultArray();

        log_message('debug', "getMessagesForRequest: Found " . count($rows) . " messages for request {$requestId}");

        $result = $this->hydrateFiles($rows);
        
        log_message('debug', "getMessagesForRequest: After hydrateFiles, returning " . count($result) . " messages");

        return $result;
    }

    public function addMessage($messageData)
    {
        $data = [
            'service_request_id' => (int) ($messageData['service_request_id'] ?? 0),
            'sender_id'          => (int) ($messageData['sender_id'] ?? 0),
            'message_type'       => (string) ($messageData['message_type'] ?? 'text'),
            'message'            => (string) ($messageData['message'] ?? ''),
            'is_read'            => 0,
            'is_deleted'         => 0,
        ];

        $this->db->transStart();

        $messageId = $this->insert($data, true);

        // ربط ملفات إن وُجدت (اختياري – الكنترولر عندك أصلاً يربط عبر ServiceRequestFileModel)
        if (!empty($messageData['files']) && is_array($messageData['files'])) {
            $srf   = $this->db->table('service_request_files');
            $order = 0;
            foreach ($messageData['files'] as $f) {
                $fileId   = is_array($f) ? ($f['id'] ?? $f['file_id'] ?? null) : $f;
                $fileType = is_array($f) ? ($f['file_type'] ?? 'attachment') : 'attachment';
                if ($fileId) {
                    $srf->insert([
                        'service_request_id' => (int) $data['service_request_id'],
                        'message_id'         => (int) $messageId,
                        'file_id'            => (int) $fileId,
                        'file_type'          => (string) $fileType,
                        'display_order'      => $order++,
                    ]);
                }
            }
        }

        $this->db->transComplete();
        return $messageId;
    }

    public function markAsRead($requestId, $userId)
    {
        $builder = $this->where('service_request_id', (int) $requestId)
            ->where('sender_id !=', (int) $userId);

        $this->applyNotDeletedFilter($builder, '');

        return $builder
            ->set('is_read', 1)
            ->update();
    }

    public function getUnreadCount($requestId, $userId)
    {
        $builder = $this->where('service_request_id', (int) $requestId)
            ->where('sender_id !=', (int) $userId)
            ->where('is_read', 0);

        $this->applyNotDeletedFilter($builder, '');

        return $builder->countAllResults();
    }

    public function getNewMessages($requestId, $lastMessageId, $currentUserId = null)
    {
        $requestId     = (int) $requestId;
        $lastMessageId = (int) $lastMessageId;
        $viewerId      = (int) ($currentUserId ?? (session('user_id') ?? 0));

        $builder = $this->db->table($this->table . ' m')
            ->select($this->baseSelect($viewerId), false)
            ->where('m.service_request_id', $requestId)
            ->where('m.id >', $lastMessageId);

        $this->applyNotDeletedFilter($builder, 'm');

        $rows = $builder
            ->orderBy('m.created_at', 'ASC')
            ->orderBy('m.id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->hydrateFiles($rows);
    }

    public function getOlderMessages($requestId, $beforeId, $limit = 5, $order = 'desc')
    {
        $requestId = (int) $requestId;
        $beforeId  = (int) $beforeId;
        $ord       = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $viewerId  = (int) (session('user_id') ?? 0);

        $b = $this->db->table($this->table . ' m')
            ->select($this->baseSelect($viewerId), false)
            ->where('m.service_request_id', $requestId);

        $this->applyNotDeletedFilter($b, 'm');

        if ($beforeId > 0) {
            $b->where('m.id <', $beforeId);
        }

        $rows = $b->orderBy('m.created_at', $ord)
            ->orderBy('m.id', $ord)
            ->limit((int) $limit)
            ->get()
            ->getResultArray();

        $rows = $this->hydrateFiles($rows);
        if ($ord === 'DESC') {
            $rows = array_reverse($rows); // نعيد تصاعدي
        }
        return $rows;
    }

    /* =================== مختصرات =================== */

    public function getOlderMessagesForAdmin($requestId, $beforeId, $limit = 5, $order = 'desc')
    {
        return $this->getOlderMessages($requestId, $beforeId, $limit, $order);
    }

    public function getNewMessagesForAdmin($requestId, $lastMessageId, $currentUserId = null)
    {
        return $this->getNewMessages($requestId, $lastMessageId, $currentUserId);
    }

    public function addAdminMessage($messageData)
    {
        return $this->addMessage($messageData);
    }

    public function markAsReadForAdmin($requestId, $userId)
    {
        return $this->markAsRead($requestId, $userId);
    }

    public function getUnreadCountForAdmin($requestId, $adminId)
    {
        return $this->getUnreadCount($requestId, $adminId);
    }

    public function getLastMessageForRequest($requestId)
    {
        $builder = $this->where('service_request_id', (int) $requestId);

        $this->applyNotDeletedFilter($builder, '');

        $row = $builder
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$row) return null;
        $hydrated = $this->hydrateFiles([$row]);
        return $hydrated[0] ?? $row;
    }

    public function getMessageCountForRequest($requestId)
    {
        $builder = $this->where('service_request_id', (int) $requestId);

        $this->applyNotDeletedFilter($builder, '');

        return $builder->countAllResults();
    }

    public function deleteMessage($messageId)
    {
        return $this->update((int) $messageId, [
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** (اختياري) خريطة آخر رسالة لكل طلب لاستخدامها في قوائم الطلبات */
    public function getLastMessagesMap(array $requestIds): array
    {
        if (empty($requestIds)) return [];

        $idsList = implode(',', array_map('intval', $requestIds));
        $sql = "
            SELECT m.service_request_id, m.id, m.message, m.message_type, m.created_at,
                   m.sender_id, u.name AS sender_name
            FROM service_request_messages m
            LEFT JOIN users u ON u.id = m.sender_id
            INNER JOIN (
                SELECT service_request_id, MAX(id) AS max_id
                FROM service_request_messages
                WHERE service_request_id IN ($idsList)
                GROUP BY service_request_id
            ) t ON t.max_id = m.id
        ";

        $rows = $this->db->query($sql)->getResultArray();
        $map  = [];
        foreach ($rows as $r) {
            $map[(int) $r['service_request_id']] = $r;
        }
        return $map;
    }
}
