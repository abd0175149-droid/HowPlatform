<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ServiceChatController (واجهة الطالب)
 * - topMeta
 * - listRequests (+ unread_count)
 * - requestDetails
 * - listMessages (+ is_read)
 * - readUpTo: يدعم last_message_id و last_id
 * - sendMessage
 * - cancelRequest
 */
class ServiceChatController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    // أقصى حجم ملف 150MB
    private const MAX_FILE_BYTES = 150 * 1024 * 1024;

    // أنواع المرفقات المسموحة
    private const ALLOWED_MIME = [
        // Images
        'image/jpeg','image/png','image/gif','image/webp',
        // Video
        'video/mp4','video/webm','video/ogg',
        // Docs
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archives
        'application/zip','application/x-rar-compressed',
        // Audio
        'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav',
    ];

    // حدود العرض الافتراضية
    private const PAGE_REQ_LIMIT = 5;   // الطلبات
    private const PAGE_MSG_LIMIT = 10;  // الرسائل

    public function __construct()
    {
        $this->db = db_connect();
    }

    /* ============================================================
     * Helpers
     * ============================================================
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
        ] as $v) {
            $id = (int) ($v ?? 0);
            if ($id > 0) return $id;
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

    /** هل منطق القراءات مُفعّل؟ */
    private function readsEnabled(): bool
    {
        return $this->tableExists('service_message_reads') && $this->tableExists('service_messages');
    }

    private function levelToPriority(int $level): string
    {
        if ($level >= 3) return 'urgent';
        if ($level === 2) return 'high';
        if ($level === 1) return 'medium';
        return 'low';
    }

    private function decideActivePlanLevel(int $userId): int
    {
        if (!$this->tableExists('user_subscriptions') || !$this->tableExists('plans')) {
            return 0;
        }

        $sql = "
            SELECT MAX(p.level) AS max_level
            FROM user_subscriptions us
            JOIN plans p ON p.id = us.plan_id
            WHERE us.user_id = ?
              AND us.status = 'active'
              AND us.start_at <= NOW()
              AND (us.end_at IS NULL OR us.end_at >= NOW())
        ";
        $row = $this->db->query($sql, [$userId])->getRowArray();
        return (int) ($row['max_level'] ?? 0);
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
        return $this->json(['success' => true, 'message' => $msg, 'data' => $data], 200);
    }

    private function fail(string $msg, int $status = 400, array $extra = []): ResponseInterface
    {
        return $this->json(['success' => false, 'message' => $msg] + $extra, $status);
    }

    private function buildRequestsBase(int $uid, string $status = '', string $q = ''): BaseBuilder
    {
        $b = $this->db->table('service_requests sr')
            ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
            ->join('courses c', 'c.id = co.course_id', 'left')
            ->join('semesters s', 's.id = co.semester_id', 'left')
            ->where('sr.user_id', $uid);

        if ($status !== '') $b->where('sr.status', $status);
        if ($q !== '') {
            $b->groupStart()
                ->like('sr.subject', $q)
                ->orLike('sr.description', $q)
            ->groupEnd();
        }
        return $b;
    }

    private function mkRef(): string
    {
        return bin2hex(random_bytes(6)); // 12 hex chars
    }

    /* ============================================================
     * Top Meta
     * ============================================================
     */
    public function topMeta(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);
            }

            $level    = $this->decideActivePlanLevel($uid);
            $priority = $this->levelToPriority($level);

            return $this->ok([
                'plan_level'      => $level,
                'priority'        => $priority,
                'canSendVoice'    => $level >= 2 ? 1 : 0,
                'upgrade_suggest' => ($level <= 1)
                    ? 'ارفع خطتك لتحصل على أولوية أعلى وإمكانية إرسال صوتيات.'
                    : null,
                'ref'             => $ref,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('خطأ داخلي', 500, ['ref' => $ref]);
        }
    }

    /* ============================================================
     * قائمة الطلبات + بحث/فلترة + “أعرض المزيد” (+ unread_count)
     * ============================================================
     */
    public function listRequests(): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) {
                return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);
            }

            $q      = trim((string) $this->request->getGet('q'));
            $status = trim((string) $this->request->getGet('status')); // '' = الكل
            $offset = max(0, (int) $this->request->getGet('offset'));
            $limit  = min(20, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_REQ_LIMIT)));

            // إجمالي السجلات
            $countBuilder = $this->buildRequestsBase($uid, $status, $q);
            $totalRow = $countBuilder
                ->select('COUNT(sr.id) AS c', false)
                ->get()
                ->getRowArray();
            $total = (int) ($totalRow['c'] ?? 0);

            // البيانات + unread_count
            $dataBuilder = $this->buildRequestsBase($uid, $status, $q)
                ->select('sr.id, sr.subject, sr.description, sr.status, sr.priority, sr.created_at,
                          co.id AS offering_id, c.name AS course_name, s.name AS semester_name');

            if ($this->readsEnabled()) {
                $escUid = (int) $uid;
                $unreadSub = "
                    (
                      SELECT COUNT(1)
                      FROM service_messages m
                      LEFT JOIN service_message_reads r
                        ON r.message_id = m.id AND r.reader_user_id = {$escUid}
                      WHERE m.service_request_id = sr.id
                        AND m.sender_user_id <> {$escUid}
                        AND r.message_id IS NULL
                    ) AS unread_count
                ";
                $dataBuilder->select($unreadSub, false);
            } else {
                $dataBuilder->select('0 AS unread_count', false);
            }

            $rows = $dataBuilder
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
                ->where('user_id', $uid)->countAllResults();

            return $this->ok([
                'items'    => $rows,
                'total'    => $total,
                'offset'   => $offset,
                'limit'    => $limit,
                'has_more' => ($offset + $limit) < $total,
                'counts'   => $counts,
                'ref'      => $ref,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('تعذر تحميل الطلبات', 500, ['ref' => $ref]);
        }
    }

    /* ============================================================
     * تفاصيل الطلب + المرفقات الأولية
     * ============================================================
     */
    public function requestDetails(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);

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
                return $this->fail('الطلب غير موجود', 404, ['ref' => $ref]);
            }

            // المرفقات الأولية
            $files = $this->db->table('service_request_files srf')
                ->select('f.id AS file_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes')
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
            return $this->fail('تعذر تحميل التفاصيل', 500, ['ref' => $ref]);
        }
    }

    /* ============================================================
     * جلب رسائل الطلب (أحدث) + before_id (+ is_read)
     * ============================================================
     */
    public function listMessages(int $reqId): ResponseInterface
    {
        $uid = $this->currentUserId();
        if ($uid <= 0) return $this->fail('غير مسجل دخول', 401);

        // تحقق من ملكية الطلب
        $exists = $this->db->table('service_requests')
            ->where(['id' => $reqId, 'user_id' => $uid])
            ->countAllResults();

        if (!$exists) return $this->fail('غير مسموح', 403);

        $beforeId = (int) ($this->request->getGet('before_id') ?? 0);
        $limit    = min(50, max(1, (int) ($this->request->getGet('limit') ?? self::PAGE_MSG_LIMIT)));

        // SELECT مع is_read (إن وُجد جدول القراءات)
        $b = $this->db->table('service_messages sm')
            ->where('sm.service_request_id', $reqId);

        $select = 'sm.id, sm.body, sm.sender_user_id, sm.created_at';

        if ($this->readsEnabled()) {
            $escUid  = (int) $uid;
            $select .= ", EXISTS(SELECT 1 FROM service_message_reads r WHERE r.message_id = sm.id AND r.reader_user_id = {$escUid}) AS is_read";
        } else {
            $select .= ", 0 AS is_read";
        }

        $b->select($select, false);

        if ($beforeId > 0) {
            $b->where('sm.id <', $beforeId);
        }

        // نجلب تنازلياً ثم نعكسه للعرض
        $rows = $b->orderBy('sm.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        // مرفقات الرسائل
        $ids = array_column($rows, 'id');
        $filesByMsg = [];

        if (!empty($ids)) {
            $files = $this->db->table('service_request_files srf')
                ->select('srf.message_id, f.id AS file_id, f.file_name, f.file_path, f.mime_type, f.file_size_bytes')
                ->join('files f', 'f.id = srf.file_id')
                ->whereIn('srf.message_id', $ids)
                ->orderBy('srf.display_order', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($files as $f) {
                if (!isset($f['message_id'])) continue;
                $filesByMsg[$f['message_id']][] = $f;
            }
        }

        // اسم المستخدم من السِّشن فقط عندما is_me = 1
        $auth = session('auth_user') ?? [];
        $meName = trim((string)($auth['name'] ?? 'أنت'));

        foreach ($rows as &$r) {
            $r['files'] = $filesByMsg[$r['id']] ?? [];
            $r['is_me'] = ((int) $r['sender_user_id'] === $uid) ? 1 : 0;
            $r['sender_name'] = $r['is_me'] ? $meName : null;
            // تأكيد نمط is_read كـ int/bool
            $r['is_read'] = (int) ($r['is_read'] ?? 0);
        }
        unset($r);

        // نعرض تصاعدي
        $rows    = array_reverse($rows);
        $hasMore = count($rows) >= $limit;

        return $this->ok([
            'items'          => $rows,
            'limit'          => $limit,
            'has_more'       => $hasMore,
            'next_before_id' => $hasMore ? ($rows[0]['id'] ?? 0) : null,
        ]);
    }

    /* ============================================================
     * وسم الرسائل "حتى آخر رقم" كمقروءة للطالب
     * ============================================================
     */
    public function readUpTo(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);

            // تحقق ملكية الطلب
            $exists = $this->db->table('service_requests')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->countAllResults();
            if (!$exists) return $this->fail('غير مسموح', 403, ['ref' => $ref]);

            // ✅ دعم كلا الاسمين: last_message_id (الواجهة) و last_id (قديم)
            $lastId = (int) ($this->request->getPost('last_message_id') ?? 0);
            if ($lastId <= 0) {
                $lastId = (int) ($this->request->getPost('last_id') ?? 0);
            }

            if ($lastId <= 0) {
                // إن لم يصلنا رقم → نأخذ آخر رسالة واردة للطالب
                $row = $this->db->table('service_messages')
                    ->select('MAX(id) AS max_id', false)
                    ->where('service_request_id', $reqId)
                    ->where('sender_user_id <>', $uid) // واردة للطالب
                    ->get()->getRowArray();
                $lastId = (int) ($row['max_id'] ?? 0);
            }

            if ($lastId <= 0) {
                // لا توجد رسائل واردة لوسمها
                return $this->ok([
                    'inserted' => 0,
                    'last_id'  => null,
                    'unread_after' => 0,
                    'ref' => $ref,
                ]);
            }

            // إدراج مجمّع (تجاهل المكرّر) لكل الرسائل الواردة حتى lastId
            $sql = "
                INSERT IGNORE INTO service_message_reads (message_id, reader_user_id, read_at)
                SELECT sm.id, ?, NOW()
                FROM service_messages sm
                LEFT JOIN service_message_reads r
                  ON r.message_id = sm.id AND r.reader_user_id = ?
                WHERE sm.service_request_id = ?
                  AND sm.sender_user_id <> ?
                  AND sm.id <= ?
                  AND r.message_id IS NULL
            ";
            $this->db->query($sql, [$uid, $uid, $reqId, $uid, $lastId]);
            $inserted = $this->db->affectedRows();

            // كم رسالة واردة تبقّت غير مقروءة بعد الوسم؟
            $sqlRemain = "
                SELECT COUNT(*) AS c
                FROM service_messages sm
                LEFT JOIN service_message_reads r
                  ON r.message_id = sm.id AND r.reader_user_id = ?
                WHERE sm.service_request_id = ?
                  AND sm.sender_user_id <> ?
                  AND r.message_id IS NULL
            ";
            $remainRow = $this->db->query($sqlRemain, [$uid, $reqId, $uid])->getRowArray();
            $remain = (int) ($remainRow['c'] ?? 0);

            return $this->ok([
                'inserted'     => $inserted,
                'last_id'      => $lastId,
                'unread_after' => $remain,
                'ref'          => $ref,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('تعذر وسم الرسائل كمقروءة', 500, ['ref' => $ref]);
        }
    }

    /* ============================================================
     * إرسال رسالة جديدة (نص/ملفات/صوت)
     * ============================================================
     */
    public function sendMessage(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);

            // تحقق ملكية الطلب
            $exists = $this->db->table('service_requests')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->countAllResults();

            if (!$exists) {
                return $this->fail('غير مسموح', 403, ['ref' => $ref]);
            }

            $body       = trim((string) $this->request->getPost('body'));
            $filesArray = $this->request->getFiles() ?: [];
            $fileField  = $filesArray['files'] ?? null;

            $hasIncomingFiles = false;
            if ($fileField instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
                $hasIncomingFiles = $fileField->isValid();
            } elseif (is_array($fileField) && !empty($fileField)) {
                foreach ($fileField as $f) {
                    if ($f instanceof \CodeIgniter\HTTP\Files\UploadedFile && $f->isValid()) {
                        $hasIncomingFiles = true; break;
                    }
                }
            }

            if ($body === '' && !$hasIncomingFiles) {
                return $this->fail('أدخل رسالة أو أرفق ملفًا', 422, ['ref' => $ref]);
            }

            // منع الصوت للمستوى أقل من 2
            $level    = $this->decideActivePlanLevel($uid);
            $hasAudio = false;
            if ($hasIncomingFiles) {
                $uploaded = is_array($fileField) ? $fileField : [$fileField];
                foreach ($uploaded as $f) {
                    if ($f instanceof \CodeIgniter\HTTP\Files\UploadedFile && $f->isValid()) {
                        $mime = (string) ($f->getMimeType() ?: '');
                        if ($mime === '' && is_string($f->getClientExtension())) {
                            $ext = strtolower($f->getClientExtension());
                            $map = ['mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'audio/webm','m4a'=>'audio/mp4'];
                            $mime = $map[$ext] ?? '';
                        }
                        if ($mime !== '' && str_starts_with($mime, 'audio/')) {
                            $hasAudio = true; break;
                        }
                    }
                }
            }
            if ($hasAudio && $level < 2) {
                return $this->fail('التسجيلات الصوتية متاحة للمشتركين بالمستوى 2 فأعلى.', 403, ['ref' => $ref]);
            }

            // إنشاء الرسالة داخل معاملة
            $this->db->transStart();

            $this->db->table('service_messages')->insert([
                'service_request_id' => $reqId,
                'sender_user_id'     => $uid,
                'body'               => ($body !== '' ? $body : null),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $msgId = (int) $this->db->insertID();

            // معالجة المرفقات (إن وُجدت)
            if ($hasIncomingFiles) {
                $uploadDir = WRITEPATH . 'uploads/service_requests/' . $reqId . '/messages/' . $msgId;
                if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->db->transRollback();
                    return $this->fail('فشل إنشاء مجلد الرفع', 500, ['ref' => $ref]);
                }

                $uploaded = is_array($fileField) ? $fileField : [$fileField];
                $order = 0;

                foreach ($uploaded as $file) {
                    if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile || !$file->isValid()) continue;
                    if ($file->getSize() > self::MAX_FILE_BYTES) continue;

                    // MIME + fallback
                    $mime = (string) ($file->getMimeType() ?: '');
                    if ($mime === '' && is_string($file->getClientExtension())) {
                        $ext = strtolower($file->getClientExtension());
                        $map = [
                            // صور
                            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
                            // فيديو
                            'mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg',
                            // مستندات
                            'pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            // أرشيفات
                            'zip'=>'application/zip','rar'=>'application/x-rar-compressed',
                            // صوتيات
                            'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'audio/webm','m4a'=>'audio/mp4',
                        ];
                        $mime = $map[$ext] ?? '';
                    }
                    if ($mime === '' || !in_array($mime, self::ALLOWED_MIME, true)) continue;

                    $newName = $file->getRandomName();
                    if (!$file->move($uploadDir, $newName)) {
                        continue;
                    }

                    $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $newName;

                    // إدراج في files
                    $fileRow = [
                        'file_name'        => $file->getClientName(),
                        'file_path'        => $absolutePath, // نخزّن مطلق (كما هو عندك)
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

                    // ربط بالرسالة
                    $this->db->table('service_request_files')->insert([
                        'service_request_id' => $reqId,
                        'message_id'         => $msgId,
                        'file_id'            => $fileId,
                        'file_type'          => 'message_attachment',
                        'display_order'      => $order++,
                    ]);
                }
            }

            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                return $this->fail('فشل إرسال الرسالة', 500, ['ref' => $ref]);
            }

            return $this->ok(['message_id' => $msgId, 'ref' => $ref], 'تم الإرسال');
        } catch (\Throwable $e) {
            return $this->fail('تعذر إرسال الرسالة', 500, ['ref' => $ref]);
        }
    }

    /* ============================================================
     * إلغاء الطلب
     * ============================================================
     */
    public function cancelRequest(int $reqId): ResponseInterface
    {
        $ref = $this->mkRef();
        try {
            $uid = $this->currentUserId();
            if ($uid <= 0) return $this->fail('غير مسجل دخول', 401, ['ref' => $ref]);

            $row = $this->db->table('service_requests')
                ->select('id, status')
                ->where(['id' => $reqId, 'user_id' => $uid])
                ->get()
                ->getRowArray();

            if (!$row) return $this->fail('غير مسموح', 403, ['ref' => $ref]);
            if (in_array($row['status'], ['resolved', 'cancelled'], true)) {
                return $this->fail('لا يمكن إلغاء هذا الطلب', 422, ['ref' => $ref]);
            }

            $ok = $this->db->table('service_requests')
                ->where('id', $reqId)
                ->update(['status' => 'cancelled']);

            if (!$ok) {
                return $this->fail('فشل إلغاء الطلب', 500, ['ref' => $ref]);
            }

            return $this->ok(['ref' => $ref], 'تم إلغاء الطلب');
        } catch (\Throwable $e) {
            return $this->fail('تعذر إلغاء الطلب', 500, ['ref' => $ref]);
        }
    }
}
