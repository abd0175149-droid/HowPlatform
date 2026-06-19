<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Libraries\RemoteUsersClient;
use App\Services\ServiceChatAttachmentRules;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

class ServiceChatController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    private RemoteUsersClient $remoteUsers;

    private const MAX_FILE_BYTES = ServiceChatAttachmentRules::MAX_FILE_BYTES;
    private const PAGE_REQ_LIMIT = 5;
    private const PAGE_MSG_LIMIT = 10;

    public function __construct()
    {
        $this->db = db_connect();
        $this->remoteUsers = new RemoteUsersClient();
    }

    private function currentUserId(): int
    {
        $s = session();
        foreach ([
            $s->get('user_id'),
            $s->get('auth_user.user_id'),
            $s->get('auth_user.id'),
            $s->get('user.id'),
            $s->get('student.user_id'),
        ] as $candidate) {
            $id = (int) ($candidate ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        return 0;
    }

    private function currentRole(): int
    {
        $s = session();
        foreach ([
            $s->get('role'),
            $s->get('auth_user.role'),
            $s->get('user.role'),
        ] as $candidate) {
            $role = (int) ($candidate ?? 0);
            if ($role > 0) {
                return $role;
            }
        }
        return 0;
    }

    private function tableExists(string $table): bool
    {
        try {
            $dbName = $this->db->getDatabase();
            $like   = $this->db->escape($table);
            $q = $this->db->query("SHOW TABLES FROM `{$dbName}` LIKE {$like}");
            return (bool) $q->getResult();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function readsEnabled(): bool
    {
        return $this->tableExists('service_message_reads') && $this->tableExists('service_messages');
    }

    private function json(array $payload, int $status = 200): ResponseInterface
    {
        $security = service('security');
        if ($security && method_exists($security, 'getTokenName')) {
            $payload['_csrf'] = [
                'name' => $security->getTokenName(),
                'hash' => $security->getHash(),
            ];
        }
        return $this->response->setJSON($payload)->setStatusCode($status);
    }

    private function ok(array $data = [], string $msg = 'OK'): ResponseInterface
    {
        return $this->json(['success' => true, 'message' => $msg, 'data' => $data]);
    }

    private function fail(string $msg, int $status = 400, array $extra = []): ResponseInterface
    {
        return $this->json(['success' => false, 'message' => $msg] + $extra, $status);
    }

    private function mkRef(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function scopeRequests(BaseBuilder $builder, int $uid, int $role): BaseBuilder
    {
        if ($role === 1) {
            return $builder;
        }
        if (in_array($role, [2, 4], true)) {
            return $builder->where('sr.assigned_to', $uid);
        }
        return $builder->where('1 = 0');
    }

    private function classifyAttachment(string $mime, string $clientName): string
    {
        return ServiceChatAttachmentRules::classify($mime, $clientName);
    }

    public function topMeta(): ResponseInterface
    {
        $ref = $this->mkRef();
        $uid = $this->currentUserId();
        if ($uid <= 0) {
            return $this->fail('Unauthorized', 401, ['ref' => $ref]);
        }
        return $this->ok([
            'plan_level'      => 0,
            'priority'        => 'normal',
            'canSendVoice'    => 1,
            'upgrade_suggest' => null,
            'ref'             => $ref,
        ]);
    }

    public function listRequests(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $q      = trim((string) $this->request->getGet('q'));
            $status = trim((string) $this->request->getGet('status'));
            $offset = max(0, (int) $this->request->getGet('offset'));
            $limit  = min(20, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_REQ_LIMIT)));

            $base = $this->db->table('service_requests sr')
                ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
                ->join('courses c', 'c.id = co.course_id', 'left')
                ->join('semesters s', 's.id = co.semester_id', 'left');

            $this->scopeRequests($base, $uid, $role);
            if ($status !== '') {
                $base->where('sr.status', $status);
            }
            if ($q !== '') {
                $base->groupStart()
                    ->like('sr.subject', $q)
                    ->orLike('sr.description', $q)
                    ->groupEnd();
            }

            $totalRow = clone $base;
            $total = (int) ($totalRow->select('COUNT(sr.id) AS c', false)->get()->getRow('c') ?? 0);

            $dataBuilder = clone $base;
            $dataBuilder->select(
                'sr.id, sr.subject, sr.description, sr.status, sr.priority, sr.created_at,' .
                'co.id AS offering_id, c.name AS course_name, s.name AS semester_name'
            );

            if ($this->readsEnabled()) {
                $escUid = (int) $uid;
                $dataBuilder->select(
                    "(SELECT COUNT(1) FROM service_messages m LEFT JOIN service_message_reads r ON r.message_id = m.id AND r.reader_user_id = {$escUid} WHERE m.service_request_id = sr.id AND m.sender_user_id <> {$escUid} AND r.message_id IS NULL) AS unread_count",
                    false
                );
            } else {
                $dataBuilder->select('0 AS unread_count', false);
            }

            $items = $dataBuilder
                ->orderBy('sr.created_at', 'DESC')
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();

            $counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'cancelled' => 0, 'all' => 0];
            foreach (array_keys($counts) as $statusKey) {
                if ($statusKey === 'all') {
                    $cb = $this->db->table('service_requests sr');
                    $counts['all'] = (int) $this->scopeRequests($cb, $uid, $role)->countAllResults();
                    continue;
                }
                $cb = $this->db->table('service_requests sr');
                $counts[$statusKey] = (int) $this->scopeRequests($cb, $uid, $role)
                    ->where('sr.status', $statusKey)
                    ->countAllResults();
            }

            return $this->ok([
                'items'    => $items,
                'total'    => $total,
                'offset'   => $offset,
                'limit'    => $limit,
                'has_more' => ($offset + $limit) < $total,
                'counts'   => $counts,
                'ref'      => $ref,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[admin.svc.list][' . $ref . '] ' . $e->getMessage());
            return $this->fail('Failed to load requests', 500, ['ref' => $ref]);
        }
    }

    public function requestDetails(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $builder = $this->db->table('service_requests sr')
                ->select('sr.*, c.name AS course_name, s.name AS semester_name')
                ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
                ->join('courses c', 'c.id = co.course_id', 'left')
                ->join('semesters s', 's.id = co.semester_id', 'left')
                ->where('sr.id', $reqId);

            $this->scopeRequests($builder, $uid, $role);
            $req = $builder->get()->getRowArray();
            if (!$req) {
                return $this->fail('Request not found', 404, ['ref' => $ref]);
            }

            $files = $this->db->table('service_request_files srf')
                ->select(
                    'f.id AS file_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes,' .
                    'srf.file_type,' .
                    "CASE WHEN srf.file_type = 'voice_recording' THEN 1 WHEN f.mime_type LIKE 'audio/%' AND f.file_name REGEXP '^voice_' THEN 1 ELSE 0 END AS is_voice"
                )
                ->join('files f', 'f.id = srf.file_id')
                ->where('srf.service_request_id', $reqId)
                ->where('srf.message_id IS NULL', null, false)
                ->orderBy('srf.display_order', 'ASC')
                ->get()
                ->getResultArray();

            return $this->ok([
                'request'     => $req,
                'attachments' => $files,
                'ref'         => $ref,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[admin.svc.details][' . $ref . '] ' . $e->getMessage());
            return $this->fail('Failed to load request details', 500, ['ref' => $ref]);
        }
    }

    public function listMessages(int $reqId): ResponseInterface
    {
        $uid  = $this->currentUserId();
        $role = $this->currentRole();
        if ($uid <= 0) {
            return $this->fail('Unauthorized', 401);
        }

        $chk = $this->db->table('service_requests sr')->where('sr.id', $reqId);
        $this->scopeRequests($chk, $uid, $role);
        if ($chk->countAllResults() === 0) {
            return $this->fail('Forbidden', 403);
        }

        $beforeId = (int) ($this->request->getGet('before_id') ?? 0);
        $limit    = min(50, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_MSG_LIMIT)));

        $select = 'sm.id, sm.body, sm.sender_user_id, sm.created_at';
        if ($this->readsEnabled()) {
            $escUid = (int) $uid;
            $select .= ", EXISTS(SELECT 1 FROM service_message_reads r WHERE r.message_id = sm.id AND r.reader_user_id = {$escUid}) AS is_read";
        } else {
            $select .= ', 0 AS is_read';
        }

        $qb = $this->db->table('service_messages sm')
            ->select($select, false)
            ->where('sm.service_request_id', $reqId);
        if ($beforeId > 0) {
            $qb->where('sm.id <', $beforeId);
        }

        $rowsDesc = $qb->orderBy('sm.id', 'DESC')
            ->limit($limit + 1)
            ->get()
            ->getResultArray();

        $hasMore = count($rowsDesc) > $limit;
        if ($hasMore) {
            array_pop($rowsDesc);
        }

        $ids = array_column($rowsDesc, 'id');
        $filesByMsg = [];
        if (!empty($ids)) {
            $files = $this->db->table('service_request_files srf')
                ->select(
                    'srf.message_id, f.id AS file_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes, srf.file_type,' .
                    "CASE WHEN srf.file_type = 'voice_recording' THEN 1 WHEN f.mime_type LIKE 'audio/%' AND f.file_name REGEXP '^voice_' THEN 1 ELSE 0 END AS is_voice"
                )
                ->join('files f', 'f.id = srf.file_id')
                ->whereIn('srf.message_id', $ids)
                ->orderBy('srf.display_order', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($files as $file) {
                $mid = (int) ($file['message_id'] ?? 0);
                if ($mid > 0) {
                    $filesByMsg[$mid][] = $file;
                }
            }
        }

        $rowsAsc = array_reverse($rowsDesc);
        $senderIds = array_unique(array_map('intval', array_column($rowsAsc, 'sender_user_id')));
        $senderData = [];
        if (!empty($senderIds)) {
            try {
                $senderData = $this->remoteUsers->resolveMany($senderIds);
            } catch (\Throwable $e) {
                $senderData = [];
            }
        }

        $authData = session('auth_user') ?? [];
        $sessionName = trim((string) ($authData['name'] ?? ''));

        foreach ($rowsAsc as &$row) {
            $mid = (int) ($row['id'] ?? 0);
            $row['files'] = $filesByMsg[$mid] ?? [];
            $senderId = (int) ($row['sender_user_id'] ?? 0);
            $row['is_me'] = ($senderId === $uid) ? 1 : 0;
            $info = $senderData[$senderId] ?? [];
            $name = trim((string) ($info['name'] ?? ''));
            if ($name === '') {
                $name = $row['is_me'] ? ($sessionName !== '' ? $sessionName : 'Me') : 'Support team';
            }
            $row['sender_name']  = $name;
            $row['sender_email'] = $info['email'] ?? null;
            $row['is_read']      = (int) ($row['is_read'] ?? 0);
        }
        unset($row);

        $nextBeforeId = null;
        if (!empty($rowsAsc)) {
            $minId = min(array_column($rowsAsc, 'id'));
            $nextBeforeId = $hasMore ? $minId : null;
        }

        return $this->ok([
            'items'          => $rowsAsc,
            'limit'          => $limit,
            'has_more'       => $hasMore,
            'next_before_id' => $nextBeforeId,
        ]);
    }

    public function readUpTo(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $chk = $this->db->table('service_requests sr')->where('sr.id', $reqId);
            $this->scopeRequests($chk, $uid, $role);
            if ($chk->countAllResults() === 0) {
                return $this->fail('Forbidden', 403, ['ref' => $ref]);
            }

            $lastId = (int) ($this->request->getPost('last_id') ?? $this->request->getPost('last_message_id') ?? 0);
            if ($lastId <= 0) {
                $row = $this->db->table('service_messages')
                    ->select('MAX(id) AS max_id', false)
                    ->where('service_request_id', $reqId)
                    ->where('sender_user_id <>', $uid)
                    ->get()->getRowArray();
                $lastId = (int) ($row['max_id'] ?? 0);
            }

            if ($lastId <= 0) {
                return $this->ok([
                    'inserted'     => 0,
                    'last_id'      => null,
                    'unread_after' => 0,
                    'ref'          => $ref,
                ]);
            }

            $sql = "
                INSERT IGNORE INTO service_message_reads (message_id, reader_user_id, read_at)
                SELECT sm.id, ?, NOW()
                FROM service_messages sm
                LEFT JOIN service_message_reads r ON r.message_id = sm.id AND r.reader_user_id = ?
                WHERE sm.service_request_id = ?
                  AND sm.sender_user_id <> ?
                  AND sm.id <= ?
                  AND r.message_id IS NULL
            ";
            $this->db->query($sql, [$uid, $uid, $reqId, $uid, $lastId]);
            $inserted = $this->db->affectedRows();

            $sqlRemain = "
                SELECT COUNT(*) AS c
                FROM service_messages sm
                LEFT JOIN service_message_reads r ON r.message_id = sm.id AND r.reader_user_id = ?
                WHERE sm.service_request_id = ?
                  AND sm.sender_user_id <> ?
                  AND r.message_id IS NULL
            ";
            $remain = (int) ($this->db->query($sqlRemain, [$uid, $reqId, $uid])->getRow('c') ?? 0);

            return $this->ok([
                'inserted'     => $inserted,
                'last_id'      => $lastId,
                'unread_after' => $remain,
                'ref'          => $ref,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[admin.svc.read][' . $ref . '] ' . $e->getMessage());
            return $this->fail('Failed to update read state', 500, ['ref' => $ref]);
        }
    }

    public function sendMessage(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $chk = $this->db->table('service_requests sr')->where('sr.id', $reqId);
            $this->scopeRequests($chk, $uid, $role);
            if ($chk->countAllResults() === 0) {
                return $this->fail('Forbidden', 403, ['ref' => $ref]);
            }

            $body       = trim((string) $this->request->getPost('body'));
            $filesArray = $this->request->getFiles() ?: [];
            $fileField  = $filesArray['files'] ?? null;

            $incoming = [];
            if ($fileField instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
                if ($fileField->isValid()) {
                    $incoming[] = $fileField;
                }
            } elseif (is_array($fileField)) {
                foreach ($fileField as $f) {
                    if ($f instanceof \CodeIgniter\HTTP\Files\UploadedFile && $f->isValid()) {
                        $incoming[] = $f;
                    }
                }
            }

            if ($body === '' && empty($incoming)) {
                return $this->fail('Message body or files required', 422, ['ref' => $ref]);
            }

            $this->db->transStart();
            $this->db->table('service_messages')->insert([
                'service_request_id' => $reqId,
                'sender_user_id'     => $uid,
                'body'               => ($body !== '' ? $body : null),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $msgId = (int) $this->db->insertID();

            if (!empty($incoming)) {
                $uploadDir = WRITEPATH . 'uploads/service_requests/' . $reqId . '/messages/' . $msgId;
                if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->db->transRollback();
                    return $this->fail('Unable to prepare upload directory', 500, ['ref' => $ref]);
                }

                $order = 0;
                foreach ($incoming as $file) {
                    if ($file->getSize() > self::MAX_FILE_BYTES) {
                        continue;
                    }
                    $mime = ServiceChatAttachmentRules::normalizeMime($file, (string) ($file->getMimeType() ?: ''));
                    if ($mime === '' || !ServiceChatAttachmentRules::isAllowedMime($mime)) {
                        continue;
                    }

                    $newName = $file->getRandomName();
                    if (!$file->move($uploadDir, $newName)) {
                        continue;
                    }

                    $clientName = (string) $file->getClientName();
                    $fileType   = $this->classifyAttachment($mime, $clientName);
                    $absolute   = $uploadDir . DIRECTORY_SEPARATOR . $newName;

                    $fileRow = [
                        'file_name'        => $clientName,
                        'file_path'        => $absolute,
                        'mime_type'        => $mime,
                        'file_size_bytes'  => (int) $file->getSize(),
                        'uploader_id'      => $uid,
                        'description'      => null,
                        'uploaded_at'      => date('Y-m-d H:i:s'),
                        'is_premium'       => 0,
                        'allow_public_view'=> null,
                    ];
                    $this->db->table('files')->insert($fileRow);
                    $fileId = (int) $this->db->insertID();

                    $this->db->table('service_request_files')->insert([
                        'service_request_id' => $reqId,
                        'message_id'         => $msgId,
                        'file_id'            => $fileId,
                        'file_type'          => $fileType,
                        'display_order'      => $order++,
                    ]);
                }
            }

            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                return $this->fail('Failed to send message (transaction error)', 500, ['ref' => $ref]);
            }

            return $this->ok(['message_id' => $msgId, 'ref' => $ref], 'Message sent.');
        } catch (\Throwable $e) {
            log_message('error', '[admin.svc.send][' . $ref . '] ' . $e->getMessage());
            return $this->fail('Failed to send message', 500, ['ref' => $ref]);
        }
    }

    public function cancelRequest(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $b = $this->db->table('service_requests sr')->where('sr.id', $reqId);
            $this->scopeRequests($b, $uid, $role);
            $row = $b->select('sr.id, sr.status')->get()->getRowArray();
            if (!$row) {
                return $this->fail('Forbidden', 403, ['ref' => $ref]);
            }
            if (in_array($row['status'], ['resolved', 'cancelled'], true)) {
                return $this->fail('Cannot cancel this request', 422, ['ref' => $ref]);
            }

            $ok = $this->db->table('service_requests')
                ->where('id', $reqId)
                ->update(['status' => 'cancelled']);
            if (!$ok) {
                return $this->fail('Failed to cancel request', 500, ['ref' => $ref]);
            }

            return $this->ok(['ref' => $ref], 'Request cancelled.');
        } catch (\Throwable $e) {
            return $this->fail('Failed to cancel request', 500, ['ref' => $ref]);
        }
    }

    public function updateStatus(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $status = trim((string) $this->request->getPost('status'));
            $allowed = ['pending', 'in_progress', 'resolved', 'cancelled'];
            if (!in_array($status, $allowed, true)) {
                return $this->fail('Invalid status', 422, ['ref' => $ref]);
            }

            $b = $this->db->table('service_requests sr')->where('sr.id', $reqId);
            $this->scopeRequests($b, $uid, $role);
            if ($b->countAllResults() === 0) {
                return $this->fail('Forbidden', 403, ['ref' => $ref]);
            }

            $ok = $this->db->table('service_requests')
                ->where('id', $reqId)
                ->update(['status' => $status]);
            if (!$ok) {
                return $this->fail('Failed to update status', 500, ['ref' => $ref]);
            }

            return $this->ok(['status' => $status, 'ref' => $ref], 'Status updated.');
        } catch (\Throwable $e) {
            return $this->fail('Failed to update status', 500, ['ref' => $ref]);
        }
    }

    public function unreadCounters(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid  = $this->currentUserId();
            $role = $this->currentRole();
            if ($uid <= 0) {
                return $this->fail('Unauthorized', 401, ['ref' => $ref]);
            }

            $sql = "
                SELECT sr.id AS request_id,
                       (
                         SELECT COUNT(*)
                         FROM service_messages sm
                         LEFT JOIN service_message_reads r ON r.message_id = sm.id AND r.reader_user_id = ?
                         WHERE sm.service_request_id = sr.id
                           AND sm.sender_user_id <> ?
                           AND r.message_id IS NULL
                       ) AS unread_count
                FROM service_requests sr
            ";

            if ($role === 1) {
                $rows = $this->db->query($sql, [$uid, $uid])->getResultArray();
            } elseif (in_array($role, [2, 4], true)) {
                $rows = $this->db->query($sql . ' WHERE sr.assigned_to = ?', [$uid, $uid, $uid])->getResultArray();
            } else {
                $rows = [];
            }

            return $this->ok(['counters' => $rows, 'ref' => $ref]);
        } catch (\Throwable $e) {
            return $this->fail('Failed to load unread counters', 500, ['ref' => $ref]);
        }
    }

    public function file(int $fileId)
    {
        $uid  = $this->currentUserId();
        $role = $this->currentRole();
        if ($uid <= 0) {
            return $this->fail('Unauthorized', 401);
        }

        $builder = $this->db->table('service_request_files srf')
            ->select('srf.service_request_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes')
            ->join('files f', 'f.id = srf.file_id')
            ->join('service_requests sr', 'sr.id = srf.service_request_id')
            ->where('srf.file_id', $fileId);
        $this->scopeRequests($builder, $uid, $role);
        $row = $builder->get()->getRowArray();
        if (!$row) {
            return $this->fail('Forbidden', 403);
        }

        $path = (string) ($row['file_path'] ?? '');
        $name = (string) ($row['file_name'] ?? ('file_' . $fileId));
        $mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
        if ($path === '' || !is_file($path)) {
            return $this->fail('OU,U.U,U? O�USO� U.U^O�U^O_', 404);
        }

        $disp = strtolower((string) ($this->request->getGet('disposition') ?? 'attachment'));
        if (!in_array($disp, ['inline', 'attachment'], true)) {
            $disp = 'attachment';
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return $this->fail('Failed to read file content', 500);
        }

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', $disp . '; filename="' . addslashes($name) . '"')
            ->setHeader('Content-Length', (string) filesize($path))
            ->setBody($content);
    }

    public function streamFile(int $fileId)
    {
        return $this->file($fileId);
    }
}
