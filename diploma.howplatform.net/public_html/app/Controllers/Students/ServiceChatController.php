<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Services\ServiceChatAttachmentRules;
use App\Services\SubscriptionService; // ← خدمة الاشتراك هي المرجع الوحيد للخطة
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

class ServiceChatController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    /** @var SubscriptionService */
    private SubscriptionService $subs; // ← حقن الخدمة

    private const MAX_FILE_BYTES = ServiceChatAttachmentRules::MAX_FILE_BYTES;
    private const PAGE_REQ_LIMIT = 5;
    private const PAGE_MSG_LIMIT = 10;

    public function __construct()
    {
        $this->db   = db_connect();
        // حقن خدمة الاشتراك مع نفس اتصال قاعدة البيانات
        $this->subs = new SubscriptionService($this->db);
    }

    /**
     * جلب معرف المستخدم من السشن بعدة مفاتيح محتملة.
     */
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

    /**
     * فحص وجود جدول (لا علاقة له بالخطة؛ يُستخدم لميزة "غير مقروء").
     */
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

    /**
     * تفعيل حالة "غير مقروء" فقط عند توفر الجداول المطلوبة.
     */
    private function readsEnabled(): bool
    {
        return $this->tableExists('service_message_reads') && $this->tableExists('service_messages');
    }

    /**
     * تحويل مستوى الخطة إلى أولوية مرئية في الواجهة.
     */
    private function levelToPriority(int $level): string
    {
        return match (true) {
            $level >= 3 => 'urgent',
            $level === 2 => 'high',
            $level === 1 => 'medium',
            default => 'low',
        };
    }

    /**
     * المرجع الوحيد لتحديد مستوى الخطة: SubscriptionService.
     * إن لم توجد خطة فعّالة تُعاد القيمة 0.
     */
    private function decideActivePlanLevel(int $userId): int
    {
        try {
            $plan = $this->subs->getActivePlanForUser($userId);
            return (int) ($plan['level'] ?? 0);
        } catch (\Throwable $e) {
            // في حال أي خطأ بالخدمة: اعتبر بلا خطة فعّالة
            return 0;
        }
    }

    /**
     * JSON helpers
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        // إرفاق CSRF إن وُجدت المساعدات
        try {
            helper('security');
            if (function_exists('csrf_token') && function_exists('csrf_hash')) {
                $payload['_csrf'] = [
                    'name' => csrf_token(),
                    'hash' => csrf_hash(),
                ];
            }
        } catch (\Throwable $e) {
            // تجاهُل أي خطأ هنا
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

    /**
     * تصنيف نوع المرفق حسب قواعد المرفقات.
     */
    private function classifyAttachment(string $mime, string $clientName): string
    {
        return ServiceChatAttachmentRules::classify($mime, $clientName);
    }

    /**
     * مُنشئ استعلام أساس لقائمة الطلبات.
     */
    private function buildRequestsBase(int $uid, string $status, string $q): BaseBuilder
    {
        $builder = $this->db->table('service_requests sr')
            ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
            ->join('courses c', 'c.id = co.course_id', 'left')
            ->join('semesters s', 's.id = co.semester_id', 'left')
            ->where('sr.user_id', $uid);

        if ($status !== '') {
            $builder->where('sr.status', $status);
        }
        if ($q !== '') {
            $builder->groupStart()
                ->like('sr.subject', $q)
                ->orLike('sr.description', $q)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * ميتا عليا للواجهة: مستوى الخطة + أولوية + السماح بالصوت.
     * الآن تعتمد كليًا على SubscriptionService.
     */
    public function topMeta(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            // قراءة بيانات الخطة من الخدمة
            $planData = $this->subs->getActivePlanForUser($uid); // قد تعود null
            $level    = (int) ($planData['level'] ?? 0);
            $priority = $this->levelToPriority($level);

            return $this->ok([
                // الحقول القديمة — الحفاظ على التوافق مع service.js
                'plan_level'      => $level,
                'priority'        => $priority,
                'canSendVoice'    => $level >= 2 ? 1 : 0,
                'upgrade_suggest' => ($level <= 1) ? 'Voice messages need a higher plan.' : null,

                // معلومات إضافية (اختيارية في الواجهة)
                'plan_id'            => $planData['plan_id'] ?? null,
                'plan_name'          => $planData['name'] ?? null,
                'daily_download_cap' => $planData['daily_download_cap'] ?? 0,

                'ref' => $ref,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Unable to load plan details.', 500, ['ref' => $ref]);
        }
    }

    /**
     * قائمة الطلبات مع عدّادات الحلات + غير المقروء.
     */
    public function listRequests(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            $q      = trim((string) $this->request->getGet('q'));
            $status = trim((string) $this->request->getGet('status'));
            $offset = max(0, (int) $this->request->getGet('offset'));
            $limit  = min(20, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_REQ_LIMIT)));

            // قاعدة الاستعلام
            $base = $this->buildRequestsBase($uid, $status, $q);

            // إجمالي السجلات
            $cntBuilder = $this->buildRequestsBase($uid, $status, $q);
            $cntBuilder->select('sr.id');
            $total = (int) $cntBuilder->countAllResults(false);

            // بيانات الصفحة
            $dataBuilder = $this->buildRequestsBase($uid, $status, $q);
            $dataBuilder->select(
                'sr.id, sr.subject, sr.description, sr.status, sr.priority, sr.created_at,' .
                'co.id AS offering_id, c.name AS course_name, s.name AS semester_name'
            );

            if ($this->readsEnabled()) {
                $escUid = (int) $uid;
                $dataBuilder->select(
                    "(SELECT COUNT(1)
                        FROM service_messages m
                        LEFT JOIN service_message_reads r
                          ON r.message_id = m.id AND r.reader_user_id = {$escUid}
                       WHERE m.service_request_id = sr.id
                         AND m.sender_user_id <> {$escUid}
                         AND r.message_id IS NULL
                    ) AS unread_count",
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

            // عدادات الحالات
            $statuses = ['pending', 'in_progress', 'resolved', 'cancelled'];
            $counts   = [];
            foreach ($statuses as $st) {
                $counts[$st] = (int) $this->db->table('service_requests')
                    ->where(['user_id' => $uid, 'status' => $st])
                    ->countAllResults();
            }
            $counts['all'] = (int) $this->db->table('service_requests')
                ->where('user_id', $uid)
                ->countAllResults();

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
            return $this->fail('Unable to load requests.', 500, ['ref' => $ref]);
        }
    }

    /**
     * تفاصيل الطلب + مرفقات أولية (غير تابعة للرسائل).
     */
    public function requestDetails(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            $req = $this->db->table('service_requests sr')
                ->select('sr.*, c.name AS course_name, s.name AS semester_name')
                ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
                ->join('courses c', 'c.id = co.course_id', 'left')
                ->join('semesters s', 's.id = co.semester_id', 'left')
                ->where('sr.id', $reqId)
                ->where('sr.user_id', $uid)
                ->get()
                ->getRowArray();
            if (!$req) {
                return $this->fail('Request not found.', 404, ['ref' => $ref]);
            }

            $files = $this->db->table('service_request_files srf')
                ->select(
                    'f.id AS file_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes, srf.file_type,' .
                    "CASE
                        WHEN srf.file_type = 'voice_recording' THEN 1
                        WHEN f.mime_type LIKE 'audio/%' AND f.file_name REGEXP '^voice_' THEN 1
                        ELSE 0
                     END AS is_voice"
                )
                ->join('files f', 'f.id = srf.file_id')
                ->where('srf.service_request_id', $reqId)
                ->where('srf.message_id', null) // WHERE IS NULL
                ->orderBy('srf.display_order', 'ASC')
                ->get()
                ->getResultArray();

            return $this->ok([
                'request'     => $req,
                'attachments' => $files,
                'ref'         => $ref,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Unable to load request details.', 500, ['ref' => $ref]);
        }
    }

    /**
     * رسائل الطلب (paged) + إرفاق الملفات لكل رسالة.
     */
    public function listMessages(int $reqId): ResponseInterface
    {
        $uid = $this->currentUserId();
        if ($uid <= 0) {
            return $this->fail('Authentication required.', 401);
        }

        $exists = $this->db->table('service_requests')
            ->where(['id' => $reqId, 'user_id' => $uid])
            ->countAllResults();
        if (!$exists) {
            return $this->fail('Request not accessible.', 403);
        }

        $beforeId = (int) ($this->request->getGet('before_id') ?? 0);
        $limit    = min(50, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_MSG_LIMIT)));

        $select = 'sm.id, sm.body, sm.sender_user_id, sm.created_at';
        if ($this->readsEnabled()) {
            $select .= ", EXISTS(SELECT 1 FROM service_message_reads r WHERE r.message_id = sm.id AND r.reader_user_id = {$uid}) AS is_read";
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
                    "CASE
                        WHEN srf.file_type = 'voice_recording' THEN 1
                        WHEN f.mime_type LIKE 'audio/%' AND f.file_name REGEXP '^voice_' THEN 1
                        ELSE 0
                     END AS is_voice"
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
        $auth = session('auth_user') ?? [];
        $meName = trim((string) ($auth['name'] ?? 'Me'));

        foreach ($rowsAsc as &$row) {
            $mid = (int) ($row['id'] ?? 0);
            $row['files'] = $filesByMsg[$mid] ?? [];
            $row['is_me'] = ((int) ($row['sender_user_id'] ?? 0) === $uid) ? 1 : 0;
            $row['sender_name'] = $row['is_me'] ? $meName : null;
            $row['is_read'] = (int) ($row['is_read'] ?? 0);
        }
        unset($row);

        $nextBeforeId = null;
        if (!empty($rowsAsc)) {
            $minId = min(array_column($rowsAsc, 'id'));
            $nextBeforeId = $hasMore ? $minId : null;
        }

        return $this->ok([
            'items'          => $rowsAsc,
            'limit          '=> $limit,
            'has_more'       => $hasMore,
            'next_before_id' => $nextBeforeId,
        ]);
    }

    /**
     * تعليم الرسائل حتى رقم محدد كمقروء (للمتلقي الحالي).
     */
    public function readUpTo(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            $exists = $this->db->table('service_requests')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->countAllResults();
            if (!$exists) {
                return $this->fail('Request not accessible.', 403, ['ref' => $ref]);
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
            return $this->fail('Unable to update read state.', 500, ['ref' => $ref]);
        }
    }

    /**
     * عدّادات غير المقروء لكل طلب.
     */
    public function unreadCounters(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            if (!$this->readsEnabled()) {
                $rows = $this->db->table('service_requests')
                    ->select('id AS request_id, 0 AS unread_count', false)
                    ->where('user_id', $uid)
                    ->get()
                    ->getResultArray();

                return $this->ok(['counters' => $rows, 'ref' => $ref]);
            }

            $sql = "
                SELECT sr.id AS request_id,
                       (
                         SELECT COUNT(*)
                         FROM service_messages sm
                         LEFT JOIN service_message_reads r
                           ON r.message_id = sm.id AND r.reader_user_id = ?
                         WHERE sm.service_request_id = sr.id
                           AND sm.sender_user_id <> ?
                           AND r.message_id IS NULL
                       ) AS unread_count
                FROM service_requests sr
                WHERE sr.user_id = ?
            ";
            $rows = $this->db->query($sql, [$uid, $uid, $uid])->getResultArray();

            return $this->ok(['counters' => $rows, 'ref' => $ref]);
        } catch (\Throwable $e) {
            return $this->fail('Unable to load unread counters.', 500, ['ref' => $ref]);
        }
    }

    /**
     * إرسال رسالة (نص + مرفقات) مع منع رفع الصوت للمستويات الأدنى من 2.
     * يعتمد على SubscriptionService لتحديد مستوى الخطة.
     */
    public function sendMessage(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            $exists = $this->db->table('service_requests')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->countAllResults();
            if (!$exists) {
                return $this->fail('Request not accessible.', 403, ['ref' => $ref]);
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
                return $this->fail('Message body or files required.', 422, ['ref' => $ref]);
            }

            // ← منع رفع الصوت حسب الخطة من SubscriptionService
            $level = $this->decideActivePlanLevel($uid);
            if (!empty($incoming) && $level < 2) {
                foreach ($incoming as $probe) {
                    $mimeProbe = ServiceChatAttachmentRules::normalizeMime($probe, (string) ($probe->getMimeType() ?: ''));
                    $class     = ServiceChatAttachmentRules::classify($mimeProbe, (string) $probe->getClientName());
                    if ($class === 'voice_recording' || $class === 'audio') {
                        return $this->fail('Audio uploads require an upgraded plan.', 403, ['ref' => $ref]);
                    }
                }
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
                    return $this->fail('Failed to prepare upload directory.', 500, ['ref' => $ref]);
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
                    $fileType   = ServiceChatAttachmentRules::classify($mime, $clientName);

                    $fileRow = [
                        'file_name'         => $clientName,
                        'file_path'         => $uploadDir . DIRECTORY_SEPARATOR . $newName,
                        'mime_type'         => $mime,
                        'file_size_bytes'   => (int) $file->getSize(),
                        'uploader_id'       => $uid,
                        'description'       => null,
                        'uploaded_at'       => date('Y-m-d H:i:s'),
                        'is_premium'        => 0,
                        'allow_public_view' => null,
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
                return $this->fail('Failed to send message.', 500, ['ref' => $ref]);
            }

            return $this->ok(['message_id' => $msgId, 'ref' => $ref], 'Message sent.');
        } catch (\Throwable $e) {
            return $this->fail('Unexpected error while sending message.', 500, ['ref' => $ref]);
        }
    }

    /**
     * إلغاء الطلب (للعميل).
     */
    public function cancelRequest(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('Authentication required.', 401, ['ref' => $ref]);
            }

            $row = $this->db->table('service_requests')
                ->select('id, status')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->get()
                ->getRowArray();
            if (!$row) {
                return $this->fail('Request not accessible.', 403, ['ref' => $ref]);
            }
            if (in_array($row['status'], ['resolved', 'cancelled'], true)) {
                return $this->fail('Cannot cancel this request.', 422, ['ref' => $ref]);
            }

            $ok = $this->db->table('service_requests')
                ->where('id', $reqId)
                ->update(['status' => 'cancelled']);
            if (!$ok) {
                return $this->fail('Failed to cancel request.', 500, ['ref' => $ref]);
            }

            return $this->ok(['ref' => $ref], 'Request cancelled.');
        } catch (\Throwable $e) {
            return $this->fail('Unexpected error while cancelling request.', 500, ['ref' => $ref]);
        }
    }
}
