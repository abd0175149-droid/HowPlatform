<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class EmailBroadcast extends BaseController
{
    /** ======================= Tunables (mail protocol friendly) ======================= */
    private const PER_PAGE = 20;
    private const BATCH_SIZE = 8;
    private const PER_MSG_DELAY_MS = 350;
    private const BETWEEN_BATCH_DELAY_MS = 1200;

    /** الرفع */
    private const UPLOAD_DIR_REL = 'uploads/email';
    private const MAX_FILE_BYTES = 12000000;

    /** الميم تايبس */
    private array $IMAGE_MIMES = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
    private array $FILE_MIMES  = [
        'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-zip-compressed',
        'text/plain',
    ];

    /** التوكنات المدعومة */
    private array $supportedTokens = [
        '{title}', '{name}', '{email}', '{phone}', '{gender}',
        '{university}', '{specialty}', '{login_link}',
    ];

    /** =============== Helpers موحّدة للسيشن (مهم جداً) =============== */
    private function currentAdminId(): int
    {
        // معرّف المستخدم الحالي (الأدمن) المخزَّن في السيشن
        return (int) (session('user_id') ?? 0);
    }

    private function currentRoleId(): int
    {
        // الدور عندك محفوظ في session('role')
        return (int) (session('role') ?? 0);
    }

    private function isSuperAdmin(): bool
    {
        // عدّل القيمة 1 إذا كان لديك ID مختلف للسوبر أدمن
        return $this->currentRoleId() === 1;
    }
    /** =============================================================== */

    public function index(): string
    {
        return view('admins/pages/email_broadcast', ['title' => 'إرسال إشعارات بريدية']);
    }

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /* ======== القوالب ======== */

    // GET /admin/email_broadcast/templates
    public function templatesList()
    {
        $rows = $this->db->table('email_templates')
            ->select('id, name, subject, COALESCE(preheader, "") as preheader, body_template')
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'data'    => $rows,
            'csrf'    => [
                'name' => csrf_token(),
                'hash' => csrf_hash(),
            ],
        ]);
    }

    // POST /admin/email_broadcast/templates
    public function templateSave()
    {
        $payload    = $this->request->getJSON(true) ?? [];
        $name       = trim($payload['name'] ?? '');
        $subject    = (string)($payload['subject'] ?? '');
        $preheader  = (string)($payload['preheader'] ?? '');
        $body       = (string)($payload['body_template'] ?? '');
        $isShared   = (int)($payload['is_shared'] ?? 0);

        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'اسم القالب مطلوب',
                'csrf'    => ['name'=>csrf_token(),'hash'=>csrf_hash()],
            ]);
        }

        $exists = $this->db->table('email_templates')->where('name', $name)->get()->getRowArray();

        if ($exists) {
            $this->db->table('email_templates')->where('id', $exists['id'])->update([
                'subject'       => $subject,
                'preheader'     => $preheader,
                'body_template' => $body,
                'is_shared'     => $isShared,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$exists['id'];
        } else {
            $this->db->table('email_templates')->insert([
                'name'          => $name,
                'subject'       => $subject,
                'preheader'     => $preheader,
                'body_template' => $body,
                'is_shared'     => $isShared,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->db->insertID();
        }

        return $this->response->setJSON([
            'success' => true,
            'id'      => $id,
            'message' => 'تم حفظ القالب',
            'csrf'    => ['name'=>csrf_token(),'hash'=>csrf_hash()],
        ]);
    }

    // DELETE /admin/email_broadcast/templates/{id}
    public function templateDelete($id = null)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'معرّف غير صالح',
                'csrf'    => ['name'=>csrf_token(),'hash'=>csrf_hash()],
            ]);
        }

        $affected = $this->db->table('email_templates')->where('id', $id)->delete();

        return $this->response->setJSON([
            'success' => (bool)$affected,
            'message' => $affected ? 'تم حذف القالب' : 'القالب غير موجود',
            'csrf'    => ['name'=>csrf_token(),'hash'=>csrf_hash()],
        ]);
    }

    /* ============================================================== */
    /*                        Helpers: Paths/Files                    */
    /* ============================================================== */

    private function uploadDirAbs(): string
    {
        $dir = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::UPLOAD_DIR_REL . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function publicUrl(string $storedName): string
    {
        return base_url(self::UPLOAD_DIR_REL . '/' . ltrim($storedName, '/'));
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\.\-\_\s]+/u', '', $name) ?? 'file';
        return trim($name) ?: 'file';
    }

    private function asciiFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'file';
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');

        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if ($tmp !== false && $tmp !== null) $base = $tmp;
        }
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? 'file';
        $base = trim($base, '._-') ?: 'file';

        return $ext !== '' ? ($base . '.' . $ext) : $base;
    }

    /* ============================================================== */
    /*                     Helpers: Unified Recipients                */
    /* ============================================================== */

    private function usersBaseQuery(\CodeIgniter\Database\BaseConnection $db): \CodeIgniter\Database\BaseBuilder
    {
        $b = $db->table('users u')
            ->select([
                'u.id', 'u.name', 'u.email', 'u.phone_number', 'u.gender',
                'u.is_complete', 'u.last_login_at',
                'universities.name AS university_name',
                'specialties.name  AS specialty_name',
            ])
            ->join('universities', 'universities.id = u.university_id', 'left')
            ->join('specialties',  'specialties.id = u.specialty_id',  'left');

        $b->where('u.email IS NOT NULL')->where('u.email <>', '');

        return $b;
    }

    private function applyRecipientFilters(
        \CodeIgniter\Database\BaseConnection $db,
        \CodeIgniter\Database\BaseBuilder $b,
        array $f
    ): void {
        if (isset($f['is_complete']) && ($f['is_complete'] === '0' || $f['is_complete'] === '1')) {
            $b->where('u.is_complete', (int) $f['is_complete']);
        }

        if (!empty($f['gender'])) {
            $g = trim((string) $f['gender']);
            if ($g === 'ذكر')  $g = 'male';
            if ($g === 'أنثى') $g = 'female';
            if (in_array($g, ['male', 'female'], true)) {
                $b->where('u.gender', $g);
            }
        }

        if (!empty($f['university_id'])) {
            $ids = array_filter(array_map('intval', explode(',', (string) $f['university_id'])));
            if ($ids) $b->whereIn('u.university_id', $ids);
        }
        if (!empty($f['specialty_id'])) {
            $ids = array_filter(array_map('intval', explode(',', (string) $f['specialty_id'])));
            if ($ids) $b->whereIn('u.specialty_id', $ids);
        }

        if (isset($f['account_type_id']) && $f['account_type_id'] !== '') {
            $acct = array_filter(array_map('intval', explode(',', (string) $f['account_type_id'])));
            if ($acct) $b->whereIn('u.account_type_id', $acct);
        }

        if (!empty($f['inactive_since'])) {
            $ts = strtotime((string) $f['inactive_since']);
            if ($ts !== false) {
                $date = date('Y-m-d', $ts);
                $b->groupStart()
                    ->where('u.last_login_at <', $date . ' 00:00:00')
                    ->orWhere('u.last_login_at', null)
                  ->groupEnd();
            }
        }

        if (isset($f['emailed_before']) && ($f['emailed_before'] === '0' || $f['emailed_before'] === '1')) {
            $emailed = $f['emailed_before'] === '1';
            $from    = !empty($f['emailed_from']) ? date('Y-m-d 00:00:00', strtotime((string)$f['emailed_from'])) : null;
            $to      = !empty($f['emailed_to'])   ? date('Y-m-d 23:59:59', strtotime((string)$f['emailed_to']))   : null;

            $timeCond = '';
            if ($from && $to)       $timeCond = ' AND r.sent_at BETWEEN ' . $db->escape($from) . ' AND ' . $db->escape($to) . ' ';
            elseif ($from)          $timeCond = ' AND r.sent_at >= ' . $db->escape($from) . ' ';
            elseif ($to)            $timeCond = ' AND r.sent_at <= ' . $db->escape($to)   . ' ';

            $existsSql = "
                EXISTS (
                    SELECT 1 FROM email_outbox_recipients r
                    JOIN email_outbox o ON o.id = r.outbox_id
                    WHERE o.dry_run = 0
                      AND o.is_test = 0
                      AND r.status = 'sent'
                      {$timeCond}
                      AND (
                          (r.user_id IS NOT NULL AND r.user_id = u.id)
                          OR (
                              r.user_id IS NULL
                              AND r.email IS NOT NULL AND r.email <> ''
                              AND u.email IS NOT NULL AND u.email <> ''
                              AND r.email COLLATE utf8mb4_unicode_ci = u.email COLLATE utf8mb4_unicode_ci
                          )
                      )
                )
            ";

            if ($emailed) {
                $b->where($existsSql, null, false);
            } else {
                $b->where("NOT {$existsSql}", null, false);
            }
        }
    }

    /* ============================================================== */
    /*                         Filters Meta                           */
    /* ============================================================== */

    public function filtersMeta(): ResponseInterface
    {
        try {
            $db = db_connect();

            $universities = $db->table('universities')->select('id, name')->orderBy('name','ASC')->get()->getResultArray();
            $specialties  = $db->table('specialties')->select('id, name')->orderBy('name','ASC')->get()->getResultArray();

            $accountTypes = [];
            if ($db->tableExists('account_types')) {
                $accountTypes = $db->table('account_types')->select('id, name')->orderBy('name','ASC')->get()->getResultArray();
            }

            $counts = ['complete' => 0, 'incomplete' => 0];
            if ($db->fieldExists('is_complete', 'users')) {
                $row = $db->table('users')->select("
                    SUM(CASE WHEN is_complete = 1 THEN 1 ELSE 0 END) AS complete,
                    SUM(CASE WHEN is_complete IS NULL OR is_complete <> 1 THEN 1 ELSE 0 END) AS incomplete
                ", false)->get()->getRowArray() ?: [];
                $counts['complete']   = (int) ($row['complete']   ?? 0);
                $counts['incomplete'] = (int) ($row['incomplete'] ?? 0);
            }

            return $this->okJson([
                'data' => [
                    'supportedTokens' => $this->supportedTokens,
                    'universities'    => $universities,
                    'specialties'     => $specialties,
                    'accountTypes'    => $accountTypes,
                    'counts'          => $counts,
                ]
            ]);
        } catch (Throwable $e) {
            log_message('error', 'filtersMeta(): ' . $e->getMessage());
            return $this->okJson([
                'data' => [
                    'supportedTokens' => $this->supportedTokens,
                    'universities'    => [],
                    'specialties'     => [],
                    'accountTypes'    => [],
                    'counts'          => ['complete'=>0, 'incomplete'=>0],
                ],
                'message' => 'تعذّر تحميل البيانات التعريفية.'
            ]);
        }
    }

    /* ============================================================== */
    /*                         Recipients (Unified)                   */
    /* ============================================================== */

    public function recipients(): ResponseInterface
    {
        try {
            $db  = db_connect();
            $req = $this->request;

            $f = [
                'is_complete'     => trim((string) $req->getGet('is_complete')),
                'gender'          => trim((string) $req->getGet('gender')),
                'university_id'   => trim((string) $req->getGet('university_id')),
                'specialty_id'    => trim((string) $req->getGet('specialty_id')),
                'account_type_id' => trim((string) $req->getGet('account_type_id')),
                'inactive_since'  => trim((string) $req->getGet('inactive_since')),
                'emailed_before'  => trim((string) $req->getGet('emailed_before')),
                'emailed_from'    => trim((string) $req->getGet('emailed_from')),
                'emailed_to'      => trim((string) $req->getGet('emailed_to')),
            ];

            $page    = max(1, (int) $req->getGet('page'));
            $perPage = max(1, min(200, (int) ($req->getGet('per_page') ?: self::PER_PAGE)));
            $offset  = ($page - 1) * $perPage;

            $base = $this->usersBaseQuery($db);
            $this->applyRecipientFilters($db, $base, $f);

            $total = (clone $base)->countAllResults();
            $rows  = $base->orderBy('u.id', 'DESC')->limit($perPage, $offset)->get()->getResultArray();
            $pages = (int) ceil(($total ?: 0) / $perPage);

            return $this->okJson(['data' => ['rows' => $rows, 'total' => $total, 'pages' => $pages]]);
        } catch (Throwable $e) {
            log_message('error', 'recipients(): ' . $e->getMessage());
            return $this->okJson(['data' => ['rows' => [], 'total' => 0, 'pages' => 0], 'message' => 'تعذّر تحميل قائمة المستخدمين.']);
        }
    }

    /* ============================================================== */
    /*                               Preview                          */
    /* ============================================================== */

    public function preview(): ResponseInterface
    {
        try {
            $data     = $this->request->getJSON(true) ?? [];
            $userId   = (int) ($data['user_id'] ?? 0);
            $subject  = trim((string) ($data['subject'] ?? 'رسالة'));
            $preIn    = trim((string) ($data['preheader'] ?? ''));
            $tpl      = (string) ($data['body_template'] ?? '');

            if ($tpl === '' || $userId <= 0) {
                return $this->failJson('النص ورقم المستخدم مطلوبان.');
            }

            $db   = db_connect();
            $user = $this->usersBaseQuery($db)->where('u.id', $userId)->get()->getRowArray();
            if (!$user) {
                return $this->failJson('لم يتم العثور على المستخدم.');
            }

            $subjectFinal   = $this->applyTokensPlain($subject, $user) ?: $subject;
            $preheaderFinal = $this->limitText($this->applyTokensPlain($preIn, $user), 140) ?: 'معاينة رسالة منصة كيف؟';

            $bodyHtml = $this->applyTokens($tpl, $user);
            $html     = $this->buildEmailHtml($subjectFinal, $bodyHtml, site_url('auth/login'), 'تسجيل الدخول', $preheaderFinal);

            return $this->okJson(['html' => $html]);
        } catch (Throwable $e) {
            log_message('error', 'preview(): ' . $e->getMessage());
            return $this->failJson('حدث خطأ أثناء إنشاء المعاينة.');
        }
    }

    /* ============================================================== */
    /*                                Send                            */
    /* ============================================================== */

    public function send(): ResponseInterface
    {
        try {
            $data       = $this->request->getJSON(true) ?? [];
            $subjectIn  = trim((string) ($data['subject'] ?? 'رسالة'));
            $tpl        = (string) ($data['body_template'] ?? '');
            $preIn      = trim((string) ($data['preheader'] ?? ''));
            $dryRun     = (bool)  ($data['dry_run'] ?? false);
            $testEmail  = trim((string) ($data['test_email'] ?? ''));
            $userIds    = is_array($data['user_ids'] ?? null) ? array_filter(array_map('intval', $data['user_ids'])) : [];
            $fileIds    = is_array($data['file_ids'] ?? null) ? array_filter(array_map('intval', $data['file_ids'])) : [];

            if ($tpl === '') {
                return $this->failJson('نص الرسالة مطلوب.');
            }

            $db      = db_connect();
            $adminId = $this->currentAdminId();
            $now     = date('Y-m-d H:i:s');

            // تجهيز المرفقات
            $attachments = $this->normalizeAttachments($this->fetchAttachmentsByIds($fileIds, $adminId));

            // إرسال اختبار
            if ($testEmail !== '') {
                $demoUser = [
                    'id' => 0, 'name' => 'مستخدم', 'email' => $testEmail, 'phone_number' => '',
                    'gender' => 'male', 'university_name' => '', 'specialty_name' => ''
                ];
                $subjectFinal   = $this->applyTokensPlain($subjectIn, $demoUser) ?: $subjectIn;
                $preheaderFinal = $this->limitText($this->applyTokensPlain($preIn, $demoUser), 140) ?: null;

                $res    = $this->sendOne($demoUser, $subjectFinal, $tpl, $dryRun, $testEmail, $preheaderFinal, $attachments);
                $ok     = (bool) ($res['ok'] ?? false);
                $reason = $ok ? '' : ($res['reason'] ?? 'unknown');

                $this->saveToOutbox(
                    $db, $adminId, $subjectIn, $preIn, $tpl, $dryRun, true,
                    1, $ok ? 1 : 0, $ok ? 0 : 1, $now,
                    [['email' => $testEmail, 'status' => $ok ? 'sent' : 'failed', 'reason' => $reason]],
                    $attachments
                );

                return $this->okJson([
                    'results' => [['id'=>0, 'email'=>$testEmail, 'status'=>$ok?'sent':'failed', 'reason'=>$reason]],
                    'sent'    => $ok && !$dryRun ? [$testEmail] : [],
                    'failed'  => $ok ? [] : [['email'=>$testEmail, 'reason'=>$reason]],
                    'preview' => $res['preview'] ?? null,
                ]);
            }

            // إرسال فعلي
            if (empty($userIds)) {
                return $this->failJson('لا توجد قائمة مستلمين محددة.');
            }

            $rows = $this->usersBaseQuery($db)->whereIn('u.id', $userIds)->get()->getResultArray();

            $sent    = [];
            $failed  = [];
            $results = [];
            $recRows = [];

            $batches = array_chunk($rows, self::BATCH_SIZE);
            $total   = count($rows);

            foreach ($batches as $bi => $batch) {
                foreach ($batch as $user) {
                    $subjectFinal   = $this->applyTokensPlain($subjectIn, $user) ?: $subjectIn;
                    $preheaderFinal = $this->limitText($this->applyTokensPlain($preIn, $user), 140) ?: null;

                    try {
                        $res = $this->sendOne($user, $subjectFinal, $tpl, $dryRun, null, $preheaderFinal, $attachments);
                    } catch (Throwable $e) {
                        $res = ['ok' => false, 'reason' => 'Exception: ' . $e->getMessage()];
                    }

                    $ok = (bool) ($res['ok'] ?? false);
                    if ($ok) {
                        if (!$dryRun) $sent[] = (int) $user['id'];
                        $results[] = ['id' => (int)$user['id'], 'email' => (string)$user['email'], 'status' => 'sent',   'reason' => ''];
                    } else {
                        $reason = (string) ($res['reason'] ?? 'unknown');
                        $failed[]  = ['id' => (int)$user['id'], 'reason' => $reason];
                        $results[] = ['id' => (int)$user['id'], 'email' => (string)$user['email'], 'status' => 'failed', 'reason' => $reason];
                    }

                    $recRows[] = [
                        'user_id' => (int)$user['id'],
                        'email'   => (string)($user['email'] ?? ''),
                        'status'  => $ok ? 'sent' : 'failed',
                        'reason'  => $ok ? null : ($res['reason'] ?? null),
                        'sent_at' => $now,
                    ];

                    if (!$dryRun) {
                        usleep(self::PER_MSG_DELAY_MS * 1000);
                    }
                }

                if (!$dryRun && $bi < (count($batches) - 1)) {
                    usleep(self::BETWEEN_BATCH_DELAY_MS * 1000);
                }
            }

            $this->saveToOutbox(
                $db, $adminId, $subjectIn, $preIn, $tpl, $dryRun, false,
                $total, count($sent), count($failed), $now, $recRows, $attachments
            );

            return $this->okJson([
                'results'         => $results,
                'sent'            => $sent,
                'failed'          => $failed,
                'total_processed' => $total,
                'batches_used'    => count($batches),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'send(): ' . $e->getMessage());
            return $this->failJson('حدث خطأ داخلي أثناء الإرسال.');
        }
    }

    /* ============================================================== */
    /*                            Outbox                              */
    /* ============================================================== */

    private function applyRoleScope(\CodeIgniter\Database\BaseBuilder $b, string $alias = ''): void
    {
        if (!$this->isSuperAdmin()) {
            $col = ($alias !== '' ? "{$alias}." : '') . 'admin_id';
            $b->where($col, $this->currentAdminId());
        }
    }

    public function outboxList(): ResponseInterface
    {
        try {
            $db = db_connect();
            $b  = $db->table('email_outbox o')->select("
                o.id, o.admin_id, o.subject, o.preheader, o.dry_run, o.is_test,
                o.total_recipients, o.sent_count, o.failed_count, o.created_at, o.finished_at
            ");
            $this->applyRoleScope($b, 'o');

            $rows = $b->orderBy('o.created_at', 'DESC')->limit(100)->get()->getResultArray();
            return $this->okJson(['data' => $rows]);
        } catch (Throwable $e) {
            log_message('error', 'outboxList(): ' . $e->getMessage());
            return $this->failJson('تعذّر تحميل البريد الصادر.');
        }
    }

    public function outboxShow(int $id): ResponseInterface
    {
        try {
            $db = db_connect();

            $h = $db->table('email_outbox o')->select('o.*')->where('o.id', $id);
            $this->applyRoleScope($h, 'o');
            $head = $h->get()->getRowArray();
            if (!$head) return $this->failJson('غير مسموح أو غير موجود.');

            $hasAvatar = $db->fieldExists('profile_image_url', 'users');
            $sel = 'r.id, r.user_id, r.email, r.status, r.reason, r.sent_at, u.name AS name';
            if ($hasAvatar) $sel .= ', u.profile_image_url AS avatar';

            $rec = $db->table('email_outbox_recipients r')
                ->select($sel)
                ->join('users u', 'u.id = r.user_id', 'left')
                ->where('r.outbox_id', $id)
                ->orderBy('r.id','ASC')
                ->get()->getResultArray();

            $files = [];
            if ($db->tableExists('email_outbox_files')) {
                $rows = $db->table('email_outbox_files of')
                    ->select('of.file_id, f.original_name, f.storage_path, f.public_url, f.mime, f.size_bytes')
                    ->join('email_files f', 'f.id = of.file_id', 'inner')
                    ->where('of.outbox_id', $id)
                    ->get()->getResultArray();

                $abs = $this->uploadDirAbs();
                foreach ($rows as $r) {
                    $stored = basename((string)($r['storage_path'] ?? ''));
                    $files[] = [
                        'id'    => (int)($r['file_id'] ?? 0),
                        'name'  => (string)($r['original_name'] ?? ''),
                        'url'   => (string)($r['public_url'] ?? ($stored ? $this->publicUrl($stored) : '')),
                        'mime'  => (string)($r['mime'] ?? 'application/octet-stream'),
                        'size'  => (int)($r['size_bytes'] ?? 0),
                        'exists'=> $stored ? (is_file($abs.$stored) ? 1 : 0) : 0,
                    ];
                }
            }

            return $this->okJson(['data' => ['head' => $head, 'recipients' => $rec, 'files' => $files]]);
        } catch (Throwable $e) {
            log_message('error', 'outboxShow(): ' . $e->getMessage());
            return $this->failJson('تعذّر تحميل تفاصيل العملية.');
        }
    }

    /* ============================================================== */
    /*                              Files                             */
    /* ============================================================== */

    public function filesUpload(): ResponseInterface
    {
        try {
            $file = $this->request->getFile('file');
            $type = strtolower(trim((string) $this->request->getPost('type'))) ?: 'attachment';
            if (!$file || !$file->isValid()) return $this->failJson('ملف غير صالح.');
            if ($file->getSize() > self::MAX_FILE_BYTES) return $this->failJson('الحجم يتجاوز الحد.');

            $mime = $file->getMimeType() ?: 'application/octet-stream';
            if ($type === 'image' && !in_array($mime, $this->IMAGE_MIMES, true)) return $this->failJson('نوع صورة غير مسموح.');
            if ($type !== 'image' && !in_array($mime, $this->FILE_MIMES,  true)) return $this->failJson('نوع مرفق غير مسموح.');

            $origName = $this->sanitizeName($file->getClientName() ?? $file->getName() ?? 'file');
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext === '') $ext = 'bin';

            $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $absDir = $this->uploadDirAbs();
            $file->move($absDir, $stored, true);

            $adminId = $this->currentAdminId();
            $now     = date('Y-m-d H:i:s');
            $size    = (int) $file->getSize();
            $url     = $this->publicUrl($stored);

            $width = null; $height = null;
            if (strpos($mime, 'image/') === 0) {
                try { $dim = @getimagesize($absDir . $stored); if (is_array($dim)) { $width = $dim[0] ?? null; $height = $dim[1] ?? null; } } catch (Throwable $e) {}
            }

            $db = db_connect();
            $id = null;
            if ($db->tableExists('email_files')) {
                $db->table('email_files')->insert([
                    'admin_id'      => $adminId,
                    'original_name' => $origName,
                    'mime'          => $mime,
                    'size_bytes'    => $size,
                    'storage_path'  => $stored,
                    'public_url'    => $url,
                    'width'         => $width,
                    'height'        => $height,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $id = (int) $db->insertID();
            }

            return $this->okJson(['data' => [
                'id'          => $id,
                'name'        => $origName,
                'mime'        => $mime,
                'type'        => (strpos($mime, 'image/')===0 ? 'image':'attachment'),
                'url'         => $url,
                'stored_name' => $stored,
                'file_size'   => $size,
            ]]);
        } catch (Throwable $e) {
            log_message('error', 'filesUpload(): ' . $e->getMessage());
            return $this->failJson('تعذّر رفع الملف.');
        }
    }

    public function filesList(): ResponseInterface
    {
        try {
            $db  = db_connect();
            $b   = $db->table('email_files')->select('id, original_name, mime, size_bytes, storage_path, public_url, width, height, created_at');
            if (!$this->isSuperAdmin() && $db->fieldExists('admin_id', 'email_files')) {
                $b->where('admin_id', $this->currentAdminId());
            }

            $rows = $b->orderBy('id','DESC')->limit(100)->get()->getResultArray();
            $abs  = $this->uploadDirAbs();

            foreach ($rows as &$r) {
                $stored         = basename((string)($r['storage_path'] ?? ''));
                $r['url']       = (string)($r['public_url'] ?? ($stored ? $this->publicUrl($stored) : ''));
                $r['exists']    = ($stored && is_file($abs.$stored)) ? 1 : 0;
                $r['name']      = (string)($r['original_name'] ?? '');
                $r['mime_type'] = (string)($r['mime'] ?? 'application/octet-stream');
                $r['file_size'] = (int)($r['size_bytes'] ?? 0);
                $r['file_type'] = (strpos($r['mime'] ?? '', 'image/') === 0) ? 'image' : 'attachment';
            }
            unset($r);

            return $this->okJson(['data' => $rows]);
        } catch (Throwable $e) {
            log_message('error', 'filesList(): ' . $e->getMessage());
            return $this->failJson('تعذّر تحميل قائمة الملفات.');
        }
    }

    public function filesDownload(int $id): ResponseInterface
    {
        try {
            $db  = db_connect();
            $b   = $db->table('email_files')->where('id', $id);
            if (!$this->isSuperAdmin() && $db->fieldExists('admin_id', 'email_files')) {
                $b->where('admin_id', $this->currentAdminId());
            }

            $row = $b->get()->getRowArray();
            if (!$row) return $this->failJson('غير مسموح أو غير موجود.');

            $stored = basename((string)($row['storage_path'] ?? ''));
            $abs    = $stored ? ($this->uploadDirAbs() . $stored) : '';
            if (!$abs || !is_file($abs)) return $this->failJson('الملف غير موجود على القرص.');

            $disp = strtolower((string)($this->request->getGet('disposition') ?? '')) === 'inline' ? 'inline' : 'attachment';
            $mime = (string)($row['mime'] ?? 'application/octet-stream');
            $name = (string)($row['original_name'] ?? basename($abs));

            $this->response->setHeader('Content-Type', $mime);
            $this->response->setHeader('Content-Disposition', $disp . '; filename="' . addslashes($name) . '"');
            return $this->response->setBody(file_get_contents($abs));
        } catch (Throwable $e) {
            log_message('error', 'filesDownload(): ' . $e->getMessage());
            return $this->failJson('تعذّر تقديم الملف.');
        }
    }

    public function filesDelete(int $id): ResponseInterface
    {
        try {
            $db = db_connect();

            $b = $db->table('email_files')->where('id', $id);
            if (!$this->isSuperAdmin() && $db->fieldExists('admin_id', 'email_files')) {
                $b->where('admin_id', $this->currentAdminId());
            }

            $row = $b->get()->getRowArray();
            if (!$row) return $this->failJson('غير مسموح أو غير موجود.');

            $db->transStart();
            if ($db->tableExists('email_template_files')) $db->table('email_template_files')->where('file_id', $id)->delete();
            if ($db->tableExists('email_outbox_files'))  $db->table('email_outbox_files')->where('file_id', $id)->delete();
            $db->table('email_files')->where('id',$id)->delete();
            $db->transComplete();

            $stored = basename((string)($row['storage_path'] ?? ''));
            $abs    = $stored ? ($this->uploadDirAbs() . $stored) : '';
            if ($abs && is_file($abs)) @unlink($abs);

            return $this->okJson();
        } catch (Throwable $e) {
            log_message('error', 'filesDelete(): ' . $e->getMessage());
            return $this->failJson('تعذّر حذف الملف.');
        }
    }

    /* ============================================================== */
    /*                   Save to Outbox + Attach helpers              */
    /* ============================================================== */

    private function saveToOutbox(
        \CodeIgniter\Database\BaseConnection $db,
        int $adminId,
        string $subject,
        string $preheader,
        string $bodyTemplate,
        bool $dryRun,
        bool $isTest,
        int $totalRecipients,
        int $sentCount,
        int $failedCount,
        string $now,
        array $recipients,
        array $attachments
    ): void {
        if (!$db->tableExists('email_outbox') || !$db->tableExists('email_outbox_recipients')) return;

        $db->transStart();
        $db->table('email_outbox')->insert([
            'admin_id'        => $adminId,
            'subject'         => $subject,
            'preheader'       => ($preheader !== '' ? $preheader : null),
            'body_template'   => $bodyTemplate,
            'dry_run'         => (int)$dryRun,
            'is_test'         => (int)$isTest,
            'total_recipients'=> $totalRecipients,
            'sent_count'      => $sentCount,
            'failed_count'    => $failedCount,
            'created_at'      => $now,
            'finished_at'     => $now,
        ]);
        $outboxId = (int) $db->insertID();

        if (!empty($recipients)) {
            $batch = [];
            foreach ($recipients as $r) {
                $batch[] = [
                    'outbox_id' => $outboxId,
                    'user_id'   => $r['user_id'] ?? null,
                    'email'     => (string)($r['email'] ?? ''),
                    'status'    => ($r['status'] ?? 'sent') === 'failed' ? 'failed' : 'sent',
                    'reason'    => $r['reason'] ?? null,
                    'sent_at'   => $now,
                ];
            }
            $db->table('email_outbox_recipients')->insertBatch($batch, null, 500);
        }

        if ($db->tableExists('email_outbox_files') && !empty($attachments)) {
            $rows = [];
            foreach ($attachments as $a) {
                $rows[] = ['outbox_id' => $outboxId, 'file_id' => (int) $a['id'], 'disposition' => 'attachment'];
            }
            if ($rows) $db->table('email_outbox_files')->insertBatch($rows);
        }

        $db->transComplete();
    }

    private function fetchAttachmentsByIds(array $ids, int $adminId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];

        $db = db_connect();
        if (!$db->tableExists('email_files')) return [];

        $b = $db->table('email_files')->whereIn('id', $ids);
        if (!$this->isSuperAdmin() && $db->fieldExists('admin_id', 'email_files')) {
            $b->where('admin_id', $adminId);
        }
        $rows = $b->get()->getResultArray();

        $absBase = $this->uploadDirAbs();
        $out = [];
        foreach ($rows as $r) {
            $stored = basename((string)($r['storage_path'] ?? ''));
            $abs    = $stored ? ($absBase . $stored) : null;
            if (!$abs || !is_file($abs)) continue;

            $mime = (string)($r['mime'] ?? 'application/octet-stream');
            $out[] = [
                'id'         => (int) $r['id'],
                'abs_path'   => $abs,
                'orig_name'  => (string)($r['original_name'] ?? ($stored ?: 'file')),
                'mime'       => $mime,
                'disposition'=> 'attachment',
                'public_url' => (string)($r['public_url'] ?? $this->publicUrl($stored)),
            ];
        }
        return $out;
    }

    private function normalizeAttachments(array $list): array
    {
        $out = [];
        foreach ($list as $a) {
            $p = $a['abs_path'] ?? null;
            if (!$p || !is_file($p) || !is_readable($p)) continue;
            $out[] = [
                'id'        => (int)($a['id'] ?? 0),
                'abs_path'  => $p,
                'orig_name' => (string)($a['orig_name'] ?? basename($p)),
                'mime'      => (string)($a['mime'] ?? 'application/octet-stream'),
                'disposition' => 'attachment',
            ];
        }
        return $out;
    }

    /* ============================================================== */
    /*                        Template & Mail Utils                   */
    /* ============================================================== */

    private function tokenMap(array $user): array
    {
        $title = (isset($user['gender']) && $user['gender'] === 'female') ? 'عزيزتي' : 'عزيزي';
        return [
            '{title}'      => $title,
            '{name}'       => (string)($user['name'] ?? ''),
            '{email}'      => (string)($user['email'] ?? ''),
            '{phone}'      => (string)($user['phone_number'] ?? ''),
            '{gender}'     => (string)($user['gender'] ?? ''),
            '{university}' => (string)($user['university_name'] ?? ''),
            '{specialty}'  => (string)($user['specialty_name'] ?? ''),
            '{login_link}' => site_url('auth/login'),
        ];
    }

    private function limitText(string $s, int $limit = 140): string
    {
        return function_exists('mb_substr') ? trim(mb_substr($s, 0, $limit)) : trim(substr($s, 0, $limit));
    }

    private function applyTokensPlain(string $tpl, array $user): string
    {
        return $tpl === '' ? '' : strtr($tpl, $this->tokenMap($user));
    }

    private function applyTokens(string $tpl, array $user): string
    {
        return nl2br($this->applyTokensPlain($tpl, $user), false);
    }

    private function buildEmailHtml(string $subject, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null, ?string $preheader = null): string
    {
        return view('emails/broadcast', [
            'subject'   => $subject,
            'preheader' => $preheader ?? 'رسالة تذكير من منصة كيف؟ التعليمية',
            'greeting'  => null,
            'bodyHtml'  => $bodyHtml,
            'ctaUrl'    => $ctaUrl,
            'ctaLabel'  => $ctaLabel ?? 'تسجيل الدخول',
        ]);
    }

    private function buildAltMessage(string $subject, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null): string
    {
        $text = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $bodyHtml));
        if (!empty($ctaUrl)) $text .= "\n\n[" . ($ctaLabel ?? 'Open Link') . "] " . $ctaUrl;
        $footer = "\n\n— منصة كيف؟ التعليمية\nsupport@howplatform.net\n";
        return $subject . "\n\n" . trim($text) . $footer;
    }

    private function sendOne(
        array $user,
        string $subject,
        string $bodyTemplate,
        bool $dryRun = false,
        ?string $testEmail = null,
        ?string $preheader = null,
        array $attachments = []
    ): array {
        try {
            $to = $testEmail ?: ($user['email'] ?? null);
            if (!$to) return ['ok' => false, 'reason' => 'No recipient email'];

            $bodyHtml = $this->applyTokens($bodyTemplate, $user);
            $ctaUrl   = site_url('auth/login');
            $ctaLabel = 'تسجيل الدخول';

            $html = $this->buildEmailHtml($subject, $bodyHtml, $ctaUrl, $ctaLabel, $preheader ?? 'تذكير مهم من منصة كيف؟');
            $alt  = $this->buildAltMessage($subject, $bodyHtml, $ctaUrl, $ctaLabel);

            if ($dryRun) {
                return ['ok' => true, 'preview' => ['to'=>$to, 'subject'=>$subject, 'html'=>$html, 'alt'=>$alt]];
            }

            $email = service('email');
            $email->initialize([
                'protocol' => 'mail',
                'mailType' => 'html',
                'charset'  => 'UTF-8',
                'wordWrap' => true,
            ]);

            $cfg       = config('Email');
            $fromEmail = (string) ($cfg->fromEmail ?: 'no-reply@howplatform.net');
            $fromName  = (string) ($cfg->fromName  ?: 'منصة كيف؟');

            $email->setFrom($fromEmail, $fromName);
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($html);
            $email->setAltMessage($alt);
            $email->setHeader('List-Unsubscribe', '<mailto:support@howplatform.net>');

            foreach ($attachments as $att) {
                $path = $att['abs_path'] ?? null;
                if (!$path || !is_file($path) || !is_readable($path)) continue;
                $real = realpath($path) ?: $path;
                $name = $this->asciiFilename($att['orig_name'] ?? basename($real));
                $email->attach($real, 'attachment', $name);
            }

            $ok = $email->send();
            if (!$ok) {
                $debug  = $email->printDebugger(['headers','subject']);
                $reason = $this->extractMailFailReason($debug);
                return ['ok' => false, 'reason' => $reason];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'Exception: ' . $e->getMessage()];
        }
    }

    private function extractMailFailReason(string $debug): string
    {
        $text = preg_replace('/\s+/', ' ', strip_tags($debug)) ?? '';
        $text = trim($text);
        if ($text === '') return 'Email send failed';
        return function_exists('mb_substr') ? mb_substr($text, 0, 220) : substr($text, 0, 220);
    }

    /* ============================================================== */
    /*                              JSON helpers                      */
    /* ============================================================== */

    private function okJson(array $data = []): ResponseInterface
    {
        $name = function_exists('csrf_token') ? csrf_token() : 'csrf_token';
        $hash = function_exists('csrf_hash') ? csrf_hash() : '';
        $data['success'] = true;
        $data['csrf']    = ['name' => $name, 'hash' => $hash];
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        return $this->response->setJSON($data);
    }

    private function failJson(string $message, array $extra = []): ResponseInterface
    {
        $name = function_exists('csrf_token') ? csrf_token() : 'csrf_token';
        $hash = function_exists('csrf_hash') ? csrf_hash() : '';
        $payload = array_merge([
            'success' => false,
            'message' => $message,
            'csrf'    => ['name' => $name, 'hash' => $hash],
        ], $extra);
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->response->setHeader('Pragma', 'no-cache');
        return $this->response->setJSON($payload);
    }
}
