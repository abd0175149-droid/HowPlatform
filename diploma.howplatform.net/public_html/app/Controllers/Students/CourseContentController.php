<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Helpers\StudentViewHelper;
use App\Libraries\JwtHelper;
use App\Services\SubscriptionService; // للاطلاع على خطة المستخدم في اللوج
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class CourseContentController extends Controller
{
    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    /** @var \CodeIgniter\Cache\CacheInterface */
    private $cache;

    /** @var SubscriptionService */
    private $subs;

    // مدد الكاش (ثواني)
    private const CACHE_COURSE_DATA      = 600;   // محتوى المساق
    private const CACHE_USER_COURSES     = 1800;  // قائمة مساقات الطالب
    private const CACHE_COURSE_STRUCTURE = 3600;  // هيكل عام

    public function __construct()
    {
        $this->db    = \Config\Database::connect();
        $this->cache = \Config\Services::cache();
        $this->subs  = new SubscriptionService($this->db);
    }

    /* =========================================================
     * Utils: Debug & Cache Key Sanitize
     * =======================================================*/

    /** تحديد إن كان وضع التصحيح مفعل (query ?debug=1 أو الهيدر X-Debug: 1) */
    private function isDebug(): bool
    {
        $q = (string) $this->request->getGet('debug');
        if ($q === '1' || strtolower($q) === 'true') {
            return true;
        }
        $h = strtolower($this->request->getHeaderLine('X-Debug'));
        return ($h === '1' || $h === 'true');
    }

    /**
     * شرط إخفاء المهام الخاصّة بالأدمن — يُطبَّق على الطلاب فقط.
     * الطاقم (1=أدمن، 2=معلّم) يرى ويفتح المهام الخاصّة بالأدمن (هي لهم لاستخدام أدواتها).
     * يُدمَج داخل SQL؛ يُعيد سلسلة ثابتة (لا مدخلات مستخدم) فلا خطر حقن.
     */
    private function adminOnlyClause(): string
    {
        $role = (int) (session('role') ?? 3);
        return in_array($role, [1, 2], true) ? '' : 'AND t.is_admin_only = 0';
    }

    /** تعقيم مفاتيح الكاش لمنع محارف محجوزة */
    private function sanitizeKey(string $key): string
    {
        // محارف CI المحجوزة: {}()/\@:
        $key = preg_replace('/[{}\(\)\/\\\\@:]/', '_', $key) ?? $key;
        // تقصير احترازي إن طال
        if (strlen($key) > 120) {
            $key = substr($key, 0, 72) . '_' . substr(sha1($key), 0, 16);
        }
        return $key;
    }

    private function cacheGet(string $key)
    {
        return $this->cache->get($this->sanitizeKey($key));
    }

    private function cacheSave(string $key, $value, int $ttl): void
    {
        $this->cache->save($this->sanitizeKey($key), $value, $ttl);
    }

    /* =========================================================
     * 1) (اختياري) مساقات الطالب
     * =======================================================*/
    public function getUserCourses()
    {
        $userId = StudentViewHelper::getEffectiveUserId() ?? JwtHelper::currentUserId();
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }

        // استخدام الإعدادات الفعالة
        $universityId = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();
        $specialtyId = StudentViewHelper::getEffectiveSpecialtyId();

        if (!$universityId || !$academicYearId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الرجاء تحديد الجامعة والسنة الأكاديمية',
            ]);
        }

        $cacheKey = "user_courses_{$userId}_{$universityId}_{$academicYearId}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $sql = "
        WITH student AS (
            SELECT
                ? AS user_id,
                ? AS university_id,
                ? AS specialty_id,
                ? AS academic_year_id
        )
        SELECT DISTINCT
            co.id AS course_offering_id,
            c.id  AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            c.description AS course_description,
            s.id AS semester_id,
            s.name AS semester_name,
            COUNT(DISTINCT wc.id) AS total_weeks,
            COUNT(DISTINCT t.id)  AS total_tasks
        FROM student st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        JOIN courses c           ON c.id = co.course_id
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = co.id
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id {$this->adminOnlyClause()}
        GROUP BY co.id, c.id, c.name, c.code, c.description, s.id, s.name
        ORDER BY s.id, c.name
        ";

        $rows = $this->db->query($sql, [$userId, $universityId, $specialtyId, $academicYearId])->getResultArray();
        $data = $this->organizeCoursesBySemester($rows);

        $this->cacheSave($cacheKey, $data, self::CACHE_USER_COURSES);

        return $this->response->setJSON([
            'success'    => true,
            'data'       => $data,
            'from_cache' => false,
        ]);
    }

    /* =========================================================
     * 2) محتوى مساق معيّن
     * =======================================================*/
    public function getCourseContent($courseOfferingId = null)
    {
        $userId = StudentViewHelper::getEffectiveUserId() ?? JwtHelper::currentUserId();
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }
        if (!$courseOfferingId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Course offering ID is required']);
        }
        if (!$this->canAccessCourse($userId, (int) $courseOfferingId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied to this course']);
        }

        $debug   = $this->isDebug();
        $plan    = $this->subs->getActivePlanForUser($userId); // للّوج فقط
        $cacheKey = "course_content_{$userId}_{$courseOfferingId}";

        if (($cached = $this->cacheGet($cacheKey)) !== null && !$debug) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $raw = $this->fetchCourseContent($userId, (int) $courseOfferingId);
        if (!$raw) {
            return $this->response->setJSON(['success' => false, 'message' => 'Course content not found']);
        }

        // تمرير userId للسياسة + تفعيل اللوج التفصيلي
        $data = $this->organizeCourseContent($raw, $userId, $debug, [
            'plan'    => $plan,
            'user_id' => $userId,
            'co_id'   => (int) $courseOfferingId,
        ]);

        // لا نخزّن الـ debug في الكاش
        $dataForCache = $data;
        unset($dataForCache['debug']);

        $this->cacheSave($cacheKey, $dataForCache, self::CACHE_COURSE_DATA);

        $payload = ['success' => true, 'data' => $dataForCache, 'from_cache' => false];
        if ($debug) {
            $payload['debug'] = $data['debug'] ?? null;
        }
        return $this->response->setJSON($payload);
    }

    /**
     * يجلب البيانات الأولية لمحتوى المساق
     */
    private function fetchCourseContent(int $userId, int $courseOfferingId): array
    {
        // ⏱ Asia/Amman
        $tz       = new \DateTimeZone('Asia/Amman');
        $now      = new \DateTimeImmutable('now', $tz);
        $today    = $now->setTime(0, 0, 0);

        // نافذة إتاحة (من اليوم وحتى السبت التالي + 14 يوم)
        $dow           = (int) $today->format('w');  // 0=الأحد ... 6=السبت
        $daysToNextSat = 6 - $dow;
        if ($daysToNextSat <= 0) $daysToNextSat += 7;
        $nextSat   = $today->modify("+{$daysToNextSat} day");
        $windowEnd = $nextSat->modify('+14 day');

        $nowTs    = $now->format('Y-m-d H:i:s');
        $todayYmd = $today->format('Y-m-d');
        $winYmd   = $windowEnd->format('Y-m-d');

        // استخدام الإعدادات الفعالة
        $universityId = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();
        $specialtyId = StudentViewHelper::getEffectiveSpecialtyId();

        if (!$universityId || !$academicYearId) {
            return [];
        }

        $sql = "
        WITH
        student AS (
          SELECT
            ? AS user_id,
            ? AS university_id,
            ? AS specialty_id,
            ? AS academic_year_id
        ),
        stu_semesters AS (
          SELECT s.id AS semester_id
          FROM semesters s
          JOIN student st ON st.academic_year_id = s.academic_year_id
        ),
        stu_offerings AS (
          SELECT co.id AS course_offering_id, co.course_id, co.university_id, co.semester_id
          FROM course_offerings co
          JOIN student st       ON st.university_id = co.university_id
          JOIN stu_semesters ss ON ss.semester_id  = co.semester_id
          WHERE co.id = ?
        ),
        visible_weeks AS (
          SELECT
            wc.id    AS weekly_content_id,
            wc.course_offering_id,
            wc.week_number,
            wc.title AS title,
            wc.publish_date
          FROM weekly_contents wc
          JOIN stu_offerings so ON so.course_offering_id = wc.course_offering_id
          WHERE wc.publish_date IS NOT NULL
            AND wc.publish_date <= ?
        )
        SELECT
          -- المستخدم
          st.user_id,
          so.semester_id,
          st.university_id,
          st.specialty_id,

          -- المساق
          so.course_offering_id,
          so.course_id,
          c.name        AS course_name,
          c.code        AS course_code,
          c.description AS course_description,

          -- الأسبوع
          vw.weekly_content_id,
          vw.week_number,
          vw.title      AS week_title,
          vw.publish_date,
          CASE
            WHEN vw.publish_date <= ? THEN 'available'
            WHEN vw.publish_date >  ? THEN 'upcoming'
            ELSE 'available'
          END AS week_status,

          -- المهمة (قد تكون NULL)
          t.id                 AS task_id,
          t.title              AS task_title,
          t.description        AS task_description,
          t.visible_from_date,
          t.due_date,
          t.is_for_all_specialties,
          tt.name              AS task_type_name,

          -- الملفات (بدون file_path)
          f.id                 AS file_id,
          f.file_name,
          f.mime_type,
          f.file_size_bytes,
          f.description        AS file_description,
          COALESCE(f.is_premium, 0) AS file_is_premium,
          f.allow_public_view,
          tf.display_order

        FROM student st
        JOIN stu_offerings so ON 1
        JOIN courses c        ON c.id = so.course_id
        LEFT JOIN visible_weeks vw ON vw.course_offering_id = so.course_offering_id

        LEFT JOIN tasks t
          ON t.weekly_content_id = vw.weekly_content_id
         {$this->adminOnlyClause()}
         AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
         AND (
                COALESCE(t.is_for_all_specialties, 0) = 1
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (
                  SELECT 1 FROM task_specialties ts
                  WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id
                )
             )

        LEFT JOIN task_types tt ON tt.id = t.task_type_id
        LEFT JOIN task_files tf ON tf.task_id = t.id
        LEFT JOIN files f       ON f.id      = tf.file_id

        ORDER BY
          so.semester_id,
          so.course_id,
          vw.week_number,
          t.id,
          tf.display_order
        ";

        $params = [
            $userId,
            $universityId,
            $specialtyId,
            $academicYearId,
            $courseOfferingId,
            $winYmd,
            $todayYmd,
            $todayYmd,
            $nowTs,
        ];

        return $this->db->query($sql, $params)->getResultArray();
    }

    /* ============== تحضير البيانات للواجهة (باستخدام FileAccessPolicy) ============== */

    /**
     * @param array $ctxMeta ['plan'=>?array,'user_id'=>int,'co_id'=>int]
     */
    private function organizeCourseContent(array $raw, int $userId, bool $debug = false, array $ctxMeta = [])
    {
        if (!$raw) return null;

        $first  = $raw[0];
        $policy = service('fileAccessPolicy');

        $out = [
            'course' => [
                'course_offering_id' => (int) $first['course_offering_id'],
                'course_id'          => (int) $first['course_id'],
                'course_name'        => $first['course_name'],
                'course_code'        => $first['course_code'],
                'course_description' => $first['course_description'],
            ],
            'weeks' => [],
            'stats' => [
                'total_weeks'     => 0,
                'total_tasks'     => 0,
                'total_files'     => 0,
                'available_weeks' => 0,
                'upcoming_weeks'  => 0,
                'locked_weeks'    => 0,
            ],
        ];

        $debugLog = [
            'scope'     => 'course_content',
            'user_id'   => $ctxMeta['user_id'] ?? $userId,
            'courseOfferingId' => $ctxMeta['co_id'] ?? null,
            'plan'      => $ctxMeta['plan'] ?? null,
            'decisions' => [] // لكل ملف قرارين: stream/download
        ];

        $weeks = [];
        $stats = &$out['stats'];

        foreach ($raw as $r) {
            $weekId = $r['weekly_content_id'];
            if (!$weekId) continue;

            if (!isset($weeks[$weekId])) {
                $weeks[$weekId] = [
                    'weekly_content_id' => (int) $weekId,
                    'week_number'       => $r['week_number'],
                    'week_title'        => $r['week_title'],
                    'publish_date'      => $r['publish_date'],
                    'week_status'       => $r['week_status'],
                    'tasks'             => [],
                ];
                $stats['total_weeks']++;
                $k = $r['week_status'] . '_weeks';
                if (isset($stats[$k])) $stats[$k]++;
            }

            $taskId = $r['task_id'];
            if ($taskId && !isset($weeks[$weekId]['tasks'][$taskId])) {
                $weeks[$weekId]['tasks'][$taskId] = [
                    'task_id'               => (int) $taskId,
                    'task_title'            => $r['task_title'],
                    'task_description'      => $r['task_description'],
                    'task_type_name'        => $r['task_type_name'],
                    'visible_from_date'     => $r['visible_from_date'],
                    'due_date'              => $r['due_date'],
                    'is_for_all_specialties'=> $r['is_for_all_specialties'],
                    'files'                 => [],
                ];
                $stats['total_tasks']++;
            }

            if ($taskId && $r['file_id']) {
                $fileMeta = [
                    'id'                => (int) $r['file_id'],
                    'mime_type'         => (string) $r['mime_type'],
                    'is_premium'        => (int) $r['file_is_premium'],   // لغير AV فقط
                    'allow_public_view' => $r['allow_public_view'],       // AV فقط
                ];

                $checkView     = $policy->canAccess($userId, $fileMeta, false); // stream
                $checkDownload = $policy->canAccess($userId, $fileMeta, true);  // download

                $file = [
                    'file_id'          => (int) $r['file_id'],
                    'file_name'        => $r['file_name'],
                    'file_is_premium'  => (int) $r['file_is_premium'],
                    'display_order'    => (int) $r['display_order'],
                    'mime_type'        => $r['mime_type'],
                    'file_size_bytes'  => $r['file_size_bytes'],
                    'file_description' => $r['file_description'],

                    // الأعلام النهائية
                    'can_stream'       => (int) ((bool) ($checkView['allowed'] ?? false)),
                    'can_download'     => (int) ((bool) ($checkDownload['allowed'] ?? false)),

                    // أسباب (لإظهار بطاقات القفل/الترقية)
                    'lock_reason_stream'   => $checkView['allowed'] ? null : ($checkView['reason'] ?? null),
                    'lock_reason_download' => $checkDownload['allowed'] ? null : ($checkDownload['reason'] ?? null),

                    // REST API آمنة
                    'stream_url'       => base_url('students/course-content/stream/'   . (int) $r['file_id']),
                    'download_url'     => base_url('students/course-content/download/' . (int) $r['file_id']),
                    'check_url'        => base_url('api/files/' . (int) $r['file_id'] . '/check?action=download'),
                ];

                // تصنيفات للواجهة
                $mt = strtolower((string) ($r['mime_type'] ?? ''));
                $file['is_media'] = (int) (
                    str_starts_with($mt, 'image/') ||
                    str_starts_with($mt, 'video/') ||
                    str_starts_with($mt, 'audio/')
                );
                $file['is_pdf']   = (int) ($mt === 'application/pdf');

                $weeks[$weekId]['tasks'][$taskId]['files'][] = $file;
                $stats['total_files']++;

                // 🔎 Debug: سجل قرار السياسة لكل ملف
                if ($debug) {
                    $debugLog['decisions'][] = [
                        'task_id'   => (int) $taskId,
                        'file_id'   => (int) $r['file_id'],
                        'file_name' => $r['file_name'],
                        'mime'      => $r['mime_type'],
                        'allow_public_view' => (int) $r['allow_public_view'],
                        'is_premium'=> (int) $r['file_is_premium'],
                        'view'      => $checkView,
                        'download'  => $checkDownload,
                    ];
                }
            }
        }

        foreach ($weeks as &$w) {
            $w['tasks'] = array_values($w['tasks']);
        }
        usort($weeks, static fn ($a, $b) => (int) $a['week_number'] <=> (int) $b['week_number']);
        $out['weeks'] = array_values($weeks);

        if ($debug) {
            $out['debug'] = $debugLog;
        }

        return $out;
    }

    private function organizeCoursesBySemester(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $semId = $r['semester_id'];
            if (!isset($out[$semId])) {
                $out[$semId] = [
                    'semester_id'   => $semId,
                    'semester_name' => $r['semester_name'],
                    'courses'       => [],
                ];
            }
            $out[$semId]['courses'][] = [
                'course_offering_id' => $r['course_offering_id'],
                'course_id'          => $r['course_id'],
                'course_name'        => $r['course_name'],
                'course_code'        => $r['course_code'],
                'course_description' => $r['course_description'],
                'total_weeks'        => (int) $r['total_weeks'],
                'total_tasks'        => (int) $r['total_tasks'],
            ];
        }
        return array_values($out);
    }

    /* =========================== صلاحيات الوصول =========================== */

    private function canAccessCourse(int $userId, int $courseOfferingId): bool
    {
        // استخدام الإعدادات الفعالة
        $universityId = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();

        if (!$universityId || !$academicYearId) {
            return false;
        }

        $sql = "
        SELECT 1
        FROM semesters s
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = ?
        WHERE s.academic_year_id = ? AND co.id = ?
        LIMIT 1
        ";
        return (bool) $this->db->query($sql, [$universityId, $academicYearId, $courseOfferingId])->getRowArray();
    }

    private function canAccessTask(int $userId, int $taskId): bool
    {
        // ⏱ Asia/Amman
        $tz    = new \DateTimeZone('Asia/Amman');
        $nowTs = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        // استخدام الإعدادات الفعالة
        $universityId = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();
        $specialtyId = StudentViewHelper::getEffectiveSpecialtyId();

        if (!$universityId || !$academicYearId) {
            return false;
        }

        $sql = "
        SELECT 1
        FROM semesters s
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = ?
        JOIN weekly_contents wc  ON wc.course_offering_id = co.id
        JOIN tasks t             ON t.weekly_content_id = wc.id
        WHERE s.academic_year_id = ?
          AND t.id = ?
          {$this->adminOnlyClause()}
          AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
          AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR ? IS NULL
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = ?)
          )
        LIMIT 1
        ";
        return (bool) $this->db->query($sql, [
            $universityId, $academicYearId, $taskId, $nowTs,
            $specialtyId, $specialtyId
        ])->getRowArray();
    }

    /* ============================= تنظيف الكاش (جزئي للمستخدم) ============================= */

    public function clearUserCache($param = null)
    {
        $userId = JwtHelper::currentUserId();
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }

        $keys    = [];
        $deleted = 0;

        if ($param !== null && ctype_digit((string) $param)) {
            $coId = (int) $param;
            if (!$this->canAccessCourse($userId, $coId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Access denied to this course']);
            }

            $keys[] = "course_content_{$userId}_{$coId}";
            $keys[] = "course_stats_{$userId}_{$coId}";

            $semId = $this->db->table('course_offerings')->select('semester_id')->where('id', $coId)->get()->getRow('semester_id');
            if ($semId) {
                $keys[] = "semester_courses_{$userId}_{$semId}";
            }

            session()->remove("last_course_access_{$coId}");
        } else {
            foreach ($this->getUserCourseOfferingIds($userId) as $coId) {
                $keys[] = "course_content_{$userId}_{$coId}";
                $keys[] = "course_stats_{$userId}_{$coId}";
                session()->remove("last_course_access_{$coId}");
            }
            foreach ($this->getUserSemesterIds($userId) as $semId) {
                $keys[] = "semester_courses_{$userId}_{$semId}";
            }
            $keys[] = "user_courses_{$userId}";
        }

        $keys = array_values(array_unique($keys));
        foreach ($keys as $k) {
            $ok = $this->cache->delete($this->sanitizeKey($k));
            if ($ok === null || $ok === true) $deleted++;
        }

        return $this->response->setJSON(['success' => true, 'deleted_keys' => $deleted, 'keys' => $keys]);
    }

    private function getUserCourseOfferingIds(int $userId): array
    {
        $rows = $this->db->query("
            SELECT co.id AS course_offering_id
            FROM diploma_users du
            JOIN semesters s         ON s.academic_year_id = du.academic_year_id
            JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = du.university_id
            WHERE du.user_id = ?
        ", [$userId])->getResultArray();

        return array_map(static fn ($r) => (int) $r['course_offering_id'], $rows);
    }

    private function getUserSemesterIds(int $userId): array
    {
        $rows = $this->db->query("
            SELECT s.id AS semester_id
            FROM diploma_users du
            JOIN semesters s ON s.academic_year_id = du.academic_year_id
            WHERE du.user_id = ?
        ", [$userId])->getResultArray();

        return array_map(static fn ($r) => (int) $r['semester_id'], $rows);
    }

    /* =============================== إحصاءات =============================== */

    public function getCourseStats($courseOfferingId = null)
    {
        $userId = JwtHelper::currentUserId();
        if (!$userId || !$courseOfferingId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required parameters']);
        }
        if (!$this->canAccessCourse($userId, (int) $courseOfferingId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $cacheKey = "course_stats_{$userId}_{$courseOfferingId}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        // ⏱ Asia/Amman
        $tz        = new \DateTimeZone('Asia/Amman');
        $today     = (new \DateTimeImmutable('today', $tz));
        $next7     = $today->modify('+7 days');
        $todayYmd  = $today->format('Y-m-d');
        $next7Ymd  = $next7->format('Y-m-d');

        $sql = "
        WITH student AS (
            SELECT
                du.user_id,
                du.university_id,
                du.specialty_id,
                du.academic_year_id
            FROM diploma_users du
            WHERE du.user_id = ?
        )
        SELECT
            COUNT(DISTINCT wc.id) AS total_weeks,
            COUNT(DISTINCT t.id)  AS total_tasks,
            COUNT(DISTINCT f.id)  AS total_files,
            SUM(CASE WHEN wc.publish_date <= ? THEN 1 ELSE 0 END) AS available_weeks,
            SUM(CASE WHEN wc.publish_date >  ? AND wc.publish_date <= ? THEN 1 ELSE 0 END) AS upcoming_weeks,
            SUM(CASE WHEN wc.publish_date >  ? OR wc.publish_date IS NULL THEN 1 ELSE 0 END) AS locked_weeks
        FROM student st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = co.id
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id {$this->adminOnlyClause()}
        LEFT JOIN task_files tf      ON tf.task_id = t.id
        LEFT JOIN files f            ON f.id = tf.file_id
        WHERE co.id = ?
        ";

        $stats = $this->db->query($sql, [
            $userId,
            $todayYmd,
            $todayYmd, $next7Ymd,
            $next7Ymd,
            $courseOfferingId
        ])->getRowArray() ?: [];

        $this->cacheSave($cacheKey, $stats, 1800);

        return $this->response->setJSON(['success' => true, 'data' => $stats, 'from_cache' => false]);
    }

    /* =============================== البحث =============================== */

    public function searchCourseContent()
    {
        $userId = JwtHelper::currentUserId();

        $courseOfferingId = (int) $this->request->getGet('course_id');
        $searchQuery      = (string) $this->request->getGet('q');

        if (!$userId || !$courseOfferingId || !$searchQuery) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing search parameters']);
        }
        if (!$this->canAccessCourse($userId, $courseOfferingId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $searchQuery = trim($searchQuery);
        if (mb_strlen($searchQuery) < 2) {
            return $this->response->setJSON(['success' => false, 'message' => 'Search query too short']);
        }

        $stuSpec  = $this->db->query("SELECT specialty_id FROM diploma_users WHERE user_id = ?", [$userId])->getRow('specialty_id');
        $stIsNull = is_null($stuSpec) ? 1 : 0;
        $pattern  = "%{$searchQuery}%";

        // ⏱ Asia/Amman
        $tz    = new \DateTimeZone('Asia/Amman');
        $nowTs = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        $sql = "
        WITH st AS (
            SELECT ? AS user_id, ? AS specialty_id, ? AS st_is_null
        )
        SELECT *
        FROM (
            SELECT
                'week' AS type,
                wc.id  AS id,
                wc.title AS title,
                wc.week_number,
                NULL AS task_type
            FROM weekly_contents wc
            WHERE wc.course_offering_id = ?
              AND wc.title LIKE ?

            UNION ALL

            SELECT
                'task' AS type,
                t.id   AS id,
                t.title AS title,
                wc.week_number,
                tt.name AS task_type
            FROM tasks t
            JOIN weekly_contents wc ON wc.id = t.weekly_content_id
            LEFT JOIN task_types tt ON tt.id = t.task_type_id
            JOIN st ON 1
            WHERE wc.course_offering_id = ?
              AND (t.title LIKE ? OR t.description LIKE ?)
              {$this->adminOnlyClause()}
              AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
              AND (
                    COALESCE(t.is_for_all_specialties,0) = 1
                 OR st.st_is_null = 1
                 OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
                 OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id)
              )
        ) q
        ORDER BY q.week_number, q.type
        ";

        $params = [
            $userId, $stuSpec, $stIsNull,
            $courseOfferingId, $pattern,
            $courseOfferingId, $pattern, $pattern, $nowTs
        ];

        $rows = $this->db->query($sql, $params)->getResultArray();

        return $this->response->setJSON(['success' => true, 'data' => $rows, 'query' => $searchQuery]);
    }

    /* ============================ أحداث مساعدة ============================ */

    public function updateLastAccess($courseOfferingId = null)
    {
        $userId = JwtHelper::currentUserId();
        if (!$userId || !$courseOfferingId) {
            return $this->response->setJSON(['success' => false]);
        }
        session()->set("last_course_access_{$courseOfferingId}", time());
        return $this->response->setJSON(['success' => true]);
    }

    /** واجهة الويب: ثبّت دائمًا students/pages/course_content + صفحة التفاصيل */
    public function pages($section)
    {
        // course_content / course-content
        if ($section === 'course_content' || $section === 'course-content') {
            return view('students/pages/course_content');
        }

        // صفحة تفاصيل المهمة: task_details / task-details
        if ($section === 'task_details' || $section === 'task-details') {
            return view('students/pages/task_details');
        }

        // دعم course-<id> إن استُخدم (اختياري)
        if (preg_match('/^course-(\d+)$/', $section, $m)) {
            $coId = (int) $m[1];
            return view('students/pages/course_content', [
                'course_offering_id' => $coId,
                'section'            => $section,
            ]);
        }

        return redirect()->to('/students');
    }

    /* ========================= NEW: سياق مهمة للتفاصيل ========================= */

    public function getTaskContext($taskId = null)
    {
        $userId = StudentViewHelper::getEffectiveUserId() ?? JwtHelper::currentUserId();
        $taskId = (int) $taskId;

        if (!$userId || !$taskId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required parameters'])->setStatusCode(400);
        }

        $universityId   = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();
        $specialtyId    = StudentViewHelper::getEffectiveSpecialtyId();

        if (!$universityId || !$academicYearId) {
            return $this->response->setJSON(['success' => false, 'message' => 'الرجاء تحديد الجامعة والسنة الأكاديمية'])->setStatusCode(400);
        }

        if (!$this->canAccessTask($userId, $taskId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied'])->setStatusCode(403);
        }

        $debug = $this->isDebug();
        $plan  = $this->subs->getActivePlanForUser($userId); // للّوج

        // ⏱ Asia/Amman
        $tz    = new \DateTimeZone('Asia/Amman');
        $nowTs = (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        // معلومات المهمة + الأسبوع + المساق
        $hdrSql = "
        SELECT
            t.id                 AS task_id,
            t.title              AS task_title,
            t.task_type_id       AS task_type_id,
            t.description        AS task_description,
            t.visible_from_date,
            t.due_date,
            t.is_for_all_specialties,
            tt.name              AS task_type_name,

            wc.id                AS weekly_content_id,
            wc.week_number,
            wc.title             AS week_title,
            wc.publish_date,
            co.university_id     AS student_university_id,
            co.university_id     AS course_university_id,


            co.id                AS course_offering_id,
            c.id                 AS course_id,
            c.name               AS course_name,
            c.code               AS course_code
        FROM semesters s
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = ?
        JOIN weekly_contents wc  ON wc.course_offering_id = co.id
        JOIN tasks t             ON t.weekly_content_id = wc.id
        LEFT JOIN task_types tt  ON tt.id = t.task_type_id
        JOIN courses c           ON c.id = co.course_id
        WHERE s.academic_year_id = ?
          AND t.id = ?
          {$this->adminOnlyClause()}
          AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
          AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR ? IS NULL
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = ?)
          )
        LIMIT 1
        ";
        $hdr = $this->db->query($hdrSql, [
            $universityId, $academicYearId, $taskId, $nowTs,
            $specialtyId, $specialtyId
        ])->getRowArray();
        if (!$hdr) {
            return $this->response->setJSON(['success' => false, 'message' => 'Task not found'])->setStatusCode(404);
        }

        // قائمة مهام نفس الأسبوع (للتنقل)
        $sibSql = "
        SELECT
            t.id AS task_id,
            t.title AS task_title,
            tt.name AS task_type_name
        FROM tasks t
        LEFT JOIN task_types tt ON tt.id = t.task_type_id
        WHERE t.weekly_content_id = ?
          {$this->adminOnlyClause()}
          AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
          AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR ? IS NULL
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = ?)
          )
        ORDER BY t.id
        ";
        $siblings = $this->db->query($sibSql, [
            (int) $hdr['weekly_content_id'], $nowTs,
            $specialtyId, $specialtyId
        ])->getResultArray() ?: [];

        // فهرس السابق/التالي
        $idx = null; $prevId = null; $nextId = null;
        foreach ($siblings as $i => $row) {
            if ((int) $row['task_id'] === (int) $taskId) {
                $idx = $i;
                $prevId = $siblings[$i - 1]['task_id'] ?? null;
                $nextId = $siblings[$i + 1]['task_id'] ?? null;
                break;
            }
        }

        // ملفات المهمة
        $filesSql = "
        SELECT
            f.id   AS file_id,
            f.file_name,
            f.mime_type,
            f.file_size_bytes,
            f.description AS file_description,
            COALESCE(f.is_premium,0) AS file_is_premium,
            f.allow_public_view
        FROM task_files tf
        JOIN files f ON f.id = tf.file_id
        WHERE tf.task_id = ?
        ORDER BY tf.display_order, f.id
        ";
        $files = $this->db->query($filesSql, [$taskId])->getResultArray() ?: [];

        // سياسة الوصول
        $policy = service('fileAccessPolicy');

        $debugLog = [
            'scope'     => 'task_context',
            'user_id'   => $userId,
            'task_id'   => $taskId,
            'plan'      => $plan,
            'decisions' => [],
        ];

        foreach ($files as &$f) {
            $fileMeta = [
                'id'                => (int) $f['file_id'],
                'mime_type'         => (string) $f['mime_type'],
                'is_premium'        => (int) $f['file_is_premium'],
                'allow_public_view' => $f['allow_public_view'],
            ];

            $checkView     = $policy->canAccess($userId, $fileMeta, false); // stream
            $checkDownload = $policy->canAccess($userId, $fileMeta, true);  // download

            $f['can_stream']   = (int) ((bool) ($checkView['allowed'] ?? false));
            $f['can_download'] = (int) ((bool) ($checkDownload['allowed'] ?? false));

            $f['stream_url']   = base_url('students/course-content/stream/'   . (int) $f['file_id']);
            $f['download_url'] = base_url('students/course-content/download/' . (int) $f['file_id']);
            $f['check_url']    = base_url('api/files/' . (int) $f['file_id'] . '/check?action=download');

            $mt = strtolower((string) ($f['mime_type'] ?? ''));
            $f['is_media'] = (int) (
                str_starts_with($mt, 'image/') ||
                str_starts_with($mt, 'video/') ||
                str_starts_with($mt, 'audio/')
            );
            $f['is_pdf'] = (int) ($mt === 'application/pdf');

            $f['lock_reason_stream']   = $checkView['allowed'] ? null : ($checkView['reason'] ?? null);
            $f['lock_reason_download'] = $checkDownload['allowed'] ? null : ($checkDownload['reason'] ?? null);

            if ($debug) {
                $debugLog['decisions'][] = [
                    'file_id'   => (int) $f['file_id'],
                    'file_name' => $f['file_name'],
                    'mime'      => $f['mime_type'],
                    'allow_public_view' => (int) $f['allow_public_view'],
                    'is_premium'=> (int) $f['is_premium'],
                    'view'      => $checkView,
                    'download'  => $checkDownload,
                ];
            }
        }
        unset($f);

        $data = [
            'course' => [
                'course_offering_id' => (int) $hdr['course_offering_id'],
                'course_id'          => (int) $hdr['course_id'],
                'university_id'      => (int) $hdr['course_university_id'],
                'course_name'        => $hdr['course_name'],
                'course_code'        => $hdr['course_code'],
            ],
            'week' => [
                'weekly_content_id' => (int) $hdr['weekly_content_id'],
                'week_number'       => (int) $hdr['week_number'],
                'week_title'        => $hdr['week_title'],
                'publish_date'      => $hdr['publish_date'],
            ],
            'task' => [
                'task_id'               => (int) $hdr['task_id'],
                'task_title'            => $hdr['task_title'],
                'task_description'      => $hdr['task_description'],
                'task_type_name'        => $hdr['task_type_name'],
                'task_type_id'          => (int) $hdr['task_type_id'],   
                'visible_from_date'     => $hdr['visible_from_date'],
                'due_date'              => $hdr['due_date'],
                'is_for_all_specialties'=> $hdr['is_for_all_specialties'],
                'files'                 => $files,
            ],
            'siblings' => [
                'index'        => $idx,
                'prev_task_id' => $prevId ? (int) $prevId : null,
                'next_task_id' => $nextId ? (int) $nextId : null,
                'list'         => $siblings,
            ],
        ];

        $payload = ['success' => true, 'data' => $data];
        if ($debug) {
            $payload['debug'] = $debugLog;
        }

        return $this->response->setJSON($payload);
    }

    /* ========================= NEW: ستريم/تنزيل (Redirect للـ API) ========================= */

    public function stream($fileId = null)
    {
        $fileId = (int) $fileId;
        if ($fileId <= 0) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        // ملاحظة: تحقق نهائي من السياسة يجب أن يتم داخل API files/stream قبل إرسال الملف
        return redirect()->to(base_url('api/files/' . $fileId . '/stream'));
    }

    public function download($fileId = null)
    {
        $fileId = (int) $fileId;
        if ($fileId <= 0) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        // ملاحظة: تحقق نهائي من السياسة يجب أن يتم داخل API files/download
        return redirect()->to(base_url('api/files/' . $fileId . '/download'));
    }

    /* ============================= NEW: مسح الكاش الكُلّي على الخادم ============================= */

    /**
     * مخصّصة للطلبات AJAX عبر POST إلى /students/course-content/server-cache
     * تتطلب الهيدر: X-Requested-With: XMLHttpRequest
     */
    public function clearCache(): ResponseInterface
    {
        $request = $this->request;

        if (strtoupper($request->getMethod()) !== 'POST' || $request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            return $this->response->setStatusCode(405)->setJSON([
                'status'  => 'fail',
                'message' => 'Invalid request method.',
            ]);
        }

        $userId = null;
        try { $userId = JwtHelper::currentUserId(); } catch (\Throwable $e) {}
        if (!$userId) {
            $session = session();
            $userId  = (int) ($session->get('user_id') ?? 0);
        }
        if (!$userId) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'fail',
                'message' => 'Unauthorized.',
            ]);
        }

        $summary = [
            'cache_clean'   => false,
            'removed_files' => 0,
            'errors'        => [],
            'opcache_reset' => false,
        ];

        // 1) Driver cache
        try {
            $cache = \Config\Services::cache();
            $summary['cache_clean'] = $cache->clean();
        } catch (\Throwable $e) {
            $summary['errors'][] = 'cache->clean(): ' . $e->getMessage();
            log_message('error', '[clearCache] cache->clean() error: {err}', ['err' => $e->getMessage()]);
        }

        // 2) purge files (views/toolbar/cache)
        $paths = [WRITEPATH . 'cache', WRITEPATH . 'views', WRITEPATH . 'toolbar'];
        foreach ($paths as $dir) {
            if (!is_dir($dir)) continue;
            try {
                $summary['removed_files'] += $this->removeDirContents($dir, ['index.html', '.htaccess']);
            } catch (\Throwable $e) {
                $summary['errors'][] = "purge {$dir}: " . $e->getMessage();
                log_message('error', '[clearCache] purge error on {dir}: {err}', ['dir' => $dir, 'err' => $e->getMessage()]);
            }
        }

        // 3) OPcache reset
        try {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
                $summary['opcache_reset'] = true;
            }
        } catch (\Throwable $e) {
            $summary['errors'][] = 'opcache_reset: ' . $e->getMessage();
            log_message('error', '[clearCache] opcache_reset error: {err}', ['err' => $e->getMessage()]);
        }

        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0');

        return $this->response->setJSON([
            'status'  => 'ok',
            'message' => 'Server caches cleared.',
            'data'    => $summary,
        ]);
    }

    private function removeDirContents(string $dir, array $keepBasenames = []): int
    {
        $deleted = 0;
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();

            // نحافظ على keep files في جذر $dir فقط
            if ($fileInfo->isFile() && $fileInfo->getPath() === $dir) {
                if (in_array($fileInfo->getBasename(), $keepBasenames, true)) {
                    continue;
                }
            }

            try {
                if ($fileInfo->isDir()) {
                    if (@rmdir($path)) $deleted++;
                } else {
                    if (@unlink($path)) $deleted++;
                }
            } catch (\Throwable $e) {
                log_message('error', '[clearCache] unlink/rmdir failed: {p} => {err}', [
                    'p'   => $path,
                    'err'=> $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }
}
