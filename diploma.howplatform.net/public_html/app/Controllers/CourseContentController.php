<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class CourseContentController extends Controller
{
    private $db;
    private $cache;
    private $session;

    // مدد الكاش (ثواني)
    private const CACHE_COURSE_DATA      = 600;   // محتوى المساق
    private const CACHE_USER_COURSES     = 1800;  // قائمة مساقات الطالب
    private const CACHE_COURSE_STRUCTURE = 3600;  // هيكل عام

    public function __construct()
    {
        $this->db      = \Config\Database::connect();
        $this->cache   = \Config\Services::cache();
        $this->session = \Config\Services::session();
    }

    /* =========================================================
     * 1) مساقات الطالب (للـ Sidebar)
     * =======================================================*/
    public function getUserCourses()
    {
        $userId = $this->session->get('user_id');
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }

        $cacheKey = "user_courses_{$userId}";
        if (($cached = $this->cache->get($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $sql = "
        WITH student AS (
            SELECT u.id AS user_id, u.university_id, u.specialty_id, u.academic_year_id
            FROM users u WHERE u.id = ?
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
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id
        GROUP BY co.id, c.id, c.name, c.code, c.description, s.id, s.name
        ORDER BY s.id, c.name
        ";

        $rows = $this->db->query($sql, [$userId])->getResultArray();
        $data = $this->organizeCoursesBySemester($rows);

        $this->cache->save($cacheKey, $data, self::CACHE_USER_COURSES);

        return $this->response->setJSON(['success' => true, 'data' => $data, 'from_cache' => false]);
    }

    /* =========================================================
     * 2) محتوى مساق معيّن
     * =======================================================*/
    public function getCourseContent($courseOfferingId = null)
    {
        $userId = $this->session->get('user_id');
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }
        if (!$courseOfferingId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Course offering ID is required']);
        }
        if (!$this->canAccessCourse($userId, $courseOfferingId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied to this course']);
        }

        $cacheKey = "course_content_{$userId}_{$courseOfferingId}";
        if (($cached = $this->cache->get($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $raw = $this->fetchCourseContent($userId, $courseOfferingId);
        if (!$raw) {
            return $this->response->setJSON(['success' => false, 'message' => 'Course content not found']);
        }

        $data = $this->organizeCourseContent($raw);

        $this->cache->save($cacheKey, $data, self::CACHE_COURSE_DATA);

        return $this->response->setJSON(['success' => true, 'data' => $data, 'from_cache' => false]);
    }

    /**
     * جلب محتوى المساق من قاعدة البيانات
     * الشرط المطلوب:
     * - احسب X = اليوم (00:00)
     * - احسب Y = أول سبت بعد اليوم (إذا اليوم سبت نأخذ السبت بعد 7 أيام)
     * - نافذة العرض = من X إلى (Y + 14 يوم)
     * - اعرض أي أسبوع تاريخ بدايته (publish_date) ≤ (Y + 14)
     *   (أي داخل النافذة أو قبلها)، ولا تعرض ما بعد ذلك.
     * - لا نسقط الأسابيع الفارغة من المهام.
     * - نفلتر المهام بـ visible_from_date <= NOW() وبالاختصاصات/العمومية.
     */
    private function fetchCourseContent($userId, $courseOfferingId)
    {
        // يمكنك ضبط التوقيت إذا لزم: $tz = new \DateTimeZone('Asia/Amman');
        $today   = new \DateTimeImmutable('today'); // 00:00 بتوقيت السيرفر
        $dow     = (int)$today->format('w');        // 0=الأحد .. 6=السبت
        $daysToNextSat = 6 - $dow;
        if ($daysToNextSat <= 0) { $daysToNextSat += 7; } // أول سبت "بعد" اليوم
        $nextSat   = $today->modify("+{$daysToNextSat} day");
        $windowEnd = $nextSat->modify('+14 day');         // Y + 14

        $sql = "
        WITH
        student AS (
          SELECT
            u.id           AS user_id,
            u.university_id,
            u.specialty_id,
            u.academic_year_id,
            u.is_premium,
            u.premium_until
          FROM users u
          WHERE u.id = ?
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
            AND wc.publish_date <= ?     -- (Y + 14)
        )
        SELECT
          -- المستخدم
          st.user_id,
          so.semester_id,
          st.university_id,
          st.specialty_id,
          st.is_premium,
          st.premium_until,

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
            WHEN vw.publish_date <= ? THEN 'available'          -- <= X
            WHEN vw.publish_date >  ? THEN 'upcoming'           -- بين X و (Y+14]
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

          -- الملفات
          f.id                 AS file_id,
          f.file_name,
          (st.is_premium = 1 OR COALESCE(f.is_premium, 0) = 0) AS can_download,
          CASE WHEN (st.is_premium = 1 OR COALESCE(f.is_premium, 0) = 0)
               THEN f.file_path       ELSE NULL END AS file_path,
          CASE WHEN (st.is_premium = 1 OR COALESCE(f.is_premium, 0) = 0)
               THEN f.mime_type       ELSE NULL END AS mime_type,
          CASE WHEN (st.is_premium = 1 OR COALESCE(f.is_premium, 0) = 0)
               THEN f.file_size_bytes ELSE NULL END AS file_size_bytes,
          CASE WHEN (st.is_premium = 1 OR COALESCE(f.is_premium, 0) = 0)
               THEN f.description     ELSE NULL END AS file_description,
          f.is_premium         AS file_is_premium,
          tf.display_order

        FROM student st
        JOIN stu_offerings so ON 1
        JOIN courses c        ON c.id = so.course_id
        LEFT JOIN visible_weeks vw ON vw.course_offering_id = so.course_offering_id

        -- فلترة المهام داخل ON حتى لا نسقط الأسابيع
        LEFT JOIN tasks t
          ON t.weekly_content_id = vw.weekly_content_id
         AND (t.visible_from_date IS NULL OR t.visible_from_date <= NOW())
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
            $courseOfferingId,
            $windowEnd->format('Y-m-d'), // شرط العرض: publish_date <= (Y+14)
            $today->format('Y-m-d'),     // لحساب week_status: available إن <= X
            $today->format('Y-m-d'),     // week_status: upcoming إن > X (وهو ضمن المرئي أصلاً)
        ];

        return $this->db->query($sql, $params)->getResultArray();
    }

    /* =========================================================
     * 3) تنظيم البيانات للإخراج
     * =======================================================*/
    private function organizeCourseContent(array $raw)
    {
        if (!$raw) return null;

        $first = $raw[0];

        $out = [
            'course' => [
                'course_offering_id' => (int)$first['course_offering_id'],
                'course_id'          => (int)$first['course_id'],
                'course_name'        => $first['course_name'],
                'course_code'        => $first['course_code'],
                'course_description' => $first['course_description'],
                'user_premium'       => (int)$first['is_premium'],
                'premium_until'      => $first['premium_until'],
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

        $weeks = [];
        $stats = &$out['stats'];

        foreach ($raw as $r) {
            $weekId = $r['weekly_content_id'];
            if (!$weekId) continue;

            if (!isset($weeks[$weekId])) {
                $weeks[$weekId] = [
                    'weekly_content_id' => (int)$weekId,
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
                    'task_id'               => (int)$taskId,
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
                $file = [
                    'file_id'         => (int)$r['file_id'],
                    'file_name'       => $r['file_name'],
                    'can_download'    => (int)$r['can_download'],
                    'file_is_premium' => (int)$r['file_is_premium'],
                    'display_order'   => (int)$r['display_order'],
                ];
                if ((int)$r['can_download'] === 1) {
                    $file['file_path']        = $r['file_path'];
                    $file['mime_type']        = $r['mime_type'];
                    $file['file_size_bytes']  = $r['file_size_bytes'];
                    $file['file_description'] = $r['file_description'];
                }
                $weeks[$weekId]['tasks'][$taskId]['files'][] = $file;
                $stats['total_files']++;
            }
        }

        foreach ($weeks as &$w) {
            $w['tasks'] = array_values($w['tasks']);
        }
        usort($weeks, static fn($a, $b) => (int)$a['week_number'] <=> (int)$b['week_number']);
        $out['weeks'] = array_values($weeks);

        return $out;
    }

    /* =========================================================
     * 4) تنظيم مساقات الـSidebar حسب الفصل
     * =======================================================*/
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
                'total_weeks'        => (int)$r['total_weeks'],
                'total_tasks'        => (int)$r['total_tasks'],
            ];
        }
        return array_values($out);
    }

    /* =========================================================
     * 5) صلاحية الوصول
     * =======================================================*/
    private function canAccessCourse($userId, $courseOfferingId): bool
    {
        $sql = "
        SELECT 1
        FROM users u
        JOIN semesters s         ON s.academic_year_id = u.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = u.university_id
        WHERE u.id = ? AND co.id = ?
        LIMIT 1
        ";
        return (bool)$this->db->query($sql, [$userId, $courseOfferingId])->getRowArray();
    }

    /* =========================================================
     * 6) مسح كاش المستخدم (كامل/مساق معيّن)
     * =======================================================*/
    public function clearUserCache($param = null)
    {
        $userId = $this->session->get('user_id');
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }

        $keys    = [];
        $deleted = 0;

        if ($param !== null && ctype_digit((string)$param)) {
            $coId = (int)$param;
            if (!$this->canAccessCourse($userId, $coId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Access denied to this course']);
            }

            $keys[] = "course_content_{$userId}_{$coId}";
            $keys[] = "course_stats_{$userId}_{$coId}";

            $semId = $this->db->table('course_offerings')->select('semester_id')->where('id', $coId)->get()->getRow('semester_id');
            if ($semId) $keys[] = "semester_courses_{$userId}_{$semId}";

            $this->session->remove("last_course_access_{$coId}");
        } else {
            foreach ($this->getUserCourseOfferingIds((int)$userId) as $coId) {
                $keys[] = "course_content_{$userId}_{$coId}";
                $keys[] = "course_stats_{$userId}_{$coId}";
                $this->session->remove("last_course_access_{$coId}");
            }
            foreach ($this->getUserSemesterIds((int)$userId) as $semId) {
                $keys[] = "semester_courses_{$userId}_{$semId}";
            }
            $keys[] = "user_courses_{$userId}";
        }

        $keys = array_values(array_unique($keys));
        foreach ($keys as $k) {
            $ok = $this->cache->delete($k);
            if ($ok === null || $ok === true) $deleted++;
        }

        return $this->response->setJSON(['success' => true, 'deleted_keys' => $deleted, 'keys' => $keys]);
    }

    private function getUserCourseOfferingIds(int $userId): array
    {
        $rows = $this->db->query("
            SELECT co.id AS course_offering_id
            FROM users u
            JOIN semesters s         ON s.academic_year_id = u.academic_year_id
            JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = u.university_id
            WHERE u.id = ?
        ", [$userId])->getResultArray();

        return array_map(static fn($r) => (int)$r['course_offering_id'], $rows);
    }

    private function getUserSemesterIds(int $userId): array
    {
        $rows = $this->db->query("
            SELECT s.id AS semester_id
            FROM users u
            JOIN semesters s ON s.academic_year_id = u.academic_year_id
            WHERE u.id = ?
        ", [$userId])->getResultArray();

        return array_map(static fn($r) => (int)$r['semester_id'], $rows);
    }

    /* =========================================================
     * 7) إحصائيات سريعة
     * =======================================================*/
    public function getCourseStats($courseOfferingId = null)
    {
        $userId = $this->session->get('user_id');
        if (!$userId || !$courseOfferingId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required parameters']);
        }
        if (!$this->canAccessCourse($userId, $courseOfferingId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Access denied']);
        }

        $cacheKey = "course_stats_{$userId}_{$courseOfferingId}";
        if (($cached = $this->cache->get($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $sql = "
        WITH student AS (
            SELECT u.id AS user_id, u.university_id, u.specialty_id, u.academic_year_id
            FROM users u WHERE u.id = ?
        )
        SELECT 
            COUNT(DISTINCT wc.id) AS total_weeks,
            COUNT(DISTINCT t.id)  AS total_tasks,
            COUNT(DISTINCT f.id)  AS total_files,
            SUM(CASE WHEN wc.publish_date <= CURDATE() THEN 1 ELSE 0 END)                                       AS available_weeks,
            SUM(CASE WHEN wc.publish_date > CURDATE() AND wc.publish_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS upcoming_weeks,
            SUM(CASE WHEN wc.publish_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) OR wc.publish_date IS NULL THEN 1 ELSE 0 END)       AS locked_weeks
        FROM student st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = co.id
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id
        LEFT JOIN task_files tf      ON tf.task_id = t.id
        LEFT JOIN files f            ON f.id = tf.file_id
        WHERE co.id = ?
        ";

        $stats = $this->db->query($sql, [$userId, $courseOfferingId])->getRowArray() ?: [];
        $this->cache->save($cacheKey, $stats, 1800);

        return $this->response->setJSON(['success' => true, 'data' => $stats, 'from_cache' => false]);
    }

    /* =========================================================
     * 8) البحث داخل المساق
     * =======================================================*/
    public function searchCourseContent()
    {
        $userId           = $this->session->get('user_id');
        $courseOfferingId = $this->request->getGet('course_id');
        $searchQuery      = $this->request->getGet('q');

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

        $stuSpec  = $this->db->query("SELECT specialty_id FROM users WHERE id = ?", [$userId])->getRow('specialty_id');
        $stIsNull = is_null($stuSpec) ? 1 : 0;
        $pattern  = "%{$searchQuery}%";

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
              AND (t.visible_from_date IS NULL OR t.visible_from_date <= NOW())
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
            $courseOfferingId, $pattern, $pattern
        ];

        $rows = $this->db->query($sql, $params)->getResultArray();

        return $this->response->setJSON(['success' => true, 'data' => $rows, 'query' => $searchQuery]);
    }

    /* =========================================================
     * 9) آخر وصول
     * =======================================================*/
    public function updateLastAccess($courseOfferingId = null)
    {
        $userId = $this->session->get('user_id');
        if (!$userId || !$courseOfferingId) {
            return $this->response->setJSON(['success' => false]);
        }
        $this->session->set("last_course_access_{$courseOfferingId}", time());
        return $this->response->setJSON(['success' => true]);
    }

    /* =========================================================
     * 10) صفحة العرض (View)
     * =======================================================*/
    public function pages($section)
    {
        if ($section === 'course-content') {
            return view('student/pages/course_content');
        }

        if (preg_match('/^course-(\d+)$/', $section, $m)) {
            $coId = (int)$m[1];
            $data = ['course_offering_id' => $coId, 'section' => $section];
            return view('student/pages/course_content', $data);
        }

        return redirect()->to('/student');
    }

    /* =========================================================
     * 11) مساقات فصل معيّن (اختياري)
     * =======================================================*/
    public function getCoursesBySemester($semesterId = null)
    {
        $userId = $this->session->get('user_id');
        if (!$userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'User not authenticated']);
        }

        $cacheKey = "semester_courses_{$userId}_{$semesterId}";
        if (($cached = $this->cache->get($cacheKey)) !== null) {
            return $this->response->setJSON(['success' => true, 'data' => $cached, 'from_cache' => true]);
        }

        $where  = $semesterId ? "AND s.id = ?" : "";
        $params = $semesterId ? [$userId, $semesterId] : [$userId];

        $sql = "
        WITH student AS (
            SELECT u.id AS user_id, u.university_id, u.specialty_id, u.academic_year_id
            FROM users u WHERE u.id = ?
        )
        SELECT 
            co.id AS course_offering_id,
            c.id  AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            s.id AS semester_id,
            s.name AS semester_name,
            COUNT(DISTINCT wc.id) AS total_weeks,
            COUNT(DISTINCT t.id)  AS total_tasks
        FROM student st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        JOIN courses c           ON c.id = co.course_id
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = co.id
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id
        WHERE 1=1 {$where}
        GROUP BY co.id, c.id, c.name, c.code, s.id, s.name
        ORDER BY s.id, c.name
        ";

        $rows = $this->db->query($sql, $params)->getResultArray();
        $this->cache->save($cacheKey, $rows, self::CACHE_USER_COURSES);

        return $this->response->setJSON(['success' => true, 'data' => $rows, 'from_cache' => false]);
    }
}
