<?php
declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourseApiController extends Controller
{
    /* ===== أدوات مساعدة مشتركة ===== */

    private function j($data, int $code = 200)
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()->id;
    }

    private function fetchDiplomaProfile(int $userId): ?object
    {
        return DB::connection('diploma')->table('diploma_users')
            ->select('user_id','university_id','academic_year_id','specialty_id')
            ->where('user_id', $userId)
            ->first() ?: null;
    }

    private function canAccessCourse(int $userId, int $courseOfferingId): bool
    {
        $sql = "
        SELECT 1
        FROM diploma_users du
        JOIN semesters s         ON s.academic_year_id = du.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = du.university_id
        WHERE du.user_id = ? AND co.id = ?
        LIMIT 1";
        $row = DB::connection('diploma')->selectOne($sql, [$userId, $courseOfferingId]);
        return (bool) $row;
    }

    /* =========================================================
     * 1) مساقات الطالب (مُنظّمة حسب الفصل)
     *    GET /api/student/my-courses
     * =======================================================*/
    public function userCourses(Request $request)
    {
        $uid = $this->userId($request);
        $profile = $this->fetchDiplomaProfile($uid);
        if (!$profile) {
            return $this->j(['success'=>true,'data'=>[],'reason'=>'no_profile']);
        }

        $sql = "
        WITH student AS (
            SELECT du.user_id, du.university_id, du.specialty_id, du.academic_year_id
            FROM diploma_users du WHERE du.user_id = ?
        )
        SELECT DISTINCT
            co.id  AS course_offering_id,
            c.id   AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            c.description AS course_description,
            s.id   AS semester_id,
            s.name AS semester_name,
            COUNT(DISTINCT wc.id) AS total_weeks,
            COUNT(DISTINCT t.id)  AS total_tasks
        FROM student st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        JOIN courses c           ON c.id = co.course_id
        LEFT JOIN weekly_contents wc ON wc.course_offering_id = co.id
        LEFT JOIN tasks t            ON t.weekly_content_id  = wc.id
        GROUP BY co.id, c.id, c.name, c.code, c.description, s.id, s.name
        ORDER BY s.id, c.name";
        $rows = DB::connection('diploma')->select($sql, [$uid]);

        // تنظيم حسب الفصل
        $out = [];
        foreach ($rows as $r) {
            $sid = (int)$r->semester_id;
            if (!isset($out[$sid])) {
                $out[$sid] = [
                    'semester_id'   => $sid,
                    'semester_name' => $r->semester_name,
                    'courses'       => [],
                ];
            }
            $out[$sid]['courses'][] = [
                'course_offering_id' => (int) $r->course_offering_id,
                'course_id'          => (int) $r->course_id,
                'course_name'        => $r->course_name,
                'course_code'        => $r->course_code,
                'course_description' => $r->course_description,
                'total_weeks'        => (int) $r->total_weeks,
                'total_tasks'        => (int) $r->total_tasks,
            ];
        }
        return $this->j(['success'=>true,'data'=>array_values($out)]);
    }

    /* =========================================================
     * 2) محتوى مساق معيّن
     *    GET /api/student/courses/{co}/content
     * =======================================================*/
    public function courseContent(Request $request, int $courseOfferingId)
    {
        $uid = $this->userId($request);
        if (!$this->canAccessCourse($uid, $courseOfferingId)) {
            return $this->j(['success'=>false,'message'=>'Access denied'], 403);
        }

        // نافذة إتاحة (كما في كودك CI)
        $tz       = new \DateTimeZone('Asia/Amman');
        $now      = new \DateTimeImmutable('now', $tz);
        $today    = (new \DateTimeImmutable('today', $tz));
        $dow      = (int) $today->format('w');  // 0..6
        $daysToNextSat = 6 - $dow; if ($daysToNextSat <= 0) $daysToNextSat += 7;
        $nextSat  = $today->modify("+{$daysToNextSat} day");
        $windowEnd= $nextSat->modify('+14 day');

        $params = [
            $uid,
            $courseOfferingId,
            $windowEnd->format('Y-m-d'),  // winYmd
            $today->format('Y-m-d'),      // todayYmd
            $today->format('Y-m-d'),      // todayYmd
            $now->format('Y-m-d H:i:s'),  // nowTs
        ];

        $sql = "
        WITH
        student AS (
          SELECT du.user_id, du.university_id, du.specialty_id, du.academic_year_id
          FROM diploma_users du WHERE du.user_id = ?
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
          SELECT wc.id AS weekly_content_id, wc.course_offering_id, wc.week_number, wc.title, wc.publish_date
          FROM weekly_contents wc
          JOIN stu_offerings so ON so.course_offering_id = wc.course_offering_id
          WHERE wc.publish_date IS NOT NULL AND wc.publish_date <= ?
        )
        SELECT
          st.user_id,
          so.semester_id,
          st.university_id,
          st.specialty_id,

          so.course_offering_id,
          so.course_id,
          c.name  AS course_name,
          c.code  AS course_code,
          c.description AS course_description,

          vw.weekly_content_id,
          vw.week_number,
          vw.title      AS week_title,
          vw.publish_date,
          CASE
            WHEN vw.publish_date <= ? THEN 'available'
            WHEN vw.publish_date >  ? THEN 'upcoming'
            ELSE 'available'
          END AS week_status,

          t.id          AS task_id,
          t.title       AS task_title,
          t.description AS task_description,
          t.visible_from_date,
          t.due_date,
          t.is_for_all_specialties,
          tt.name       AS task_type_name,

          f.id          AS file_id,
          f.file_name,
          f.mime_type,
          f.file_size_bytes,
          f.description AS file_description,
          COALESCE(f.is_premium,0) AS file_is_premium,
          f.allow_public_view,
          tf.display_order
        FROM student st
        JOIN stu_offerings so ON 1
        JOIN courses c        ON c.id = so.course_id
        LEFT JOIN visible_weeks vw ON vw.course_offering_id = so.course_offering_id
        LEFT JOIN tasks t
          ON t.weekly_content_id = vw.weekly_content_id
         AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
         AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id)
         )
        LEFT JOIN task_types tt ON tt.id = t.task_type_id
        LEFT JOIN task_files tf ON tf.task_id = t.id
        LEFT JOIN files f       ON f.id      = tf.file_id
        ORDER BY so.semester_id, so.course_id, vw.week_number, t.id, tf.display_order";

        $raw = DB::connection('diploma')->select($sql, $params);
        if (!$raw) {
            return $this->j(['success'=>false,'message'=>'Course content not found'], 404);
        }

        // تنظيم الناتج للواجهة (أسابيع ← مهام ← ملفات)
        $first = $raw[0];
        $out = [
            'course' => [
                'course_offering_id' => (int) $first->course_offering_id,
                'course_id'          => (int) $first->course_id,
                'course_name'        => $first->course_name,
                'course_code'        => $first->course_code,
                'course_description' => $first->course_description,
            ],
            'weeks' => [],
            'stats' => ['total_weeks'=>0,'total_tasks'=>0,'total_files'=>0],
        ];

        $weeks = [];
        foreach ($raw as $r) {
            $wid = $r->weekly_content_id;
            if (!$wid) continue;

            if (!isset($weeks[$wid])) {
                $weeks[$wid] = [
                    'weekly_content_id' => (int) $wid,
                    'week_number'       => (int) $r->week_number,
                    'week_title'        => $r->week_title,
                    'publish_date'      => $r->publish_date,
                    'week_status'       => $r->week_status,
                    'tasks'             => [],
                ];
                $out['stats']['total_weeks']++;
            }

            $tid = $r->task_id;
            if ($tid && !isset($weeks[$wid]['tasks'][$tid])) {
                $weeks[$wid]['tasks'][$tid] = [
                    'task_id'               => (int) $tid,
                    'task_title'            => $r->task_title,
                    'task_description'      => $r->task_description,
                    'task_type_name'        => $r->task_type_name,
                    'visible_from_date'     => $r->visible_from_date,
                    'due_date'              => $r->due_date,
                    'is_for_all_specialties'=> $r->is_for_all_specialties,
                    'files'                 => [],
                ];
                $out['stats']['total_tasks']++;
            }

            if ($tid && $r->file_id) {
                $weeks[$wid]['tasks'][$tid]['files'][] = [
                    'file_id'          => (int) $r->file_id,
                    'file_name'        => $r->file_name,
                    'mime_type'        => $r->mime_type,
                    'file_size_bytes'  => $r->file_size_bytes,
                    'file_description' => $r->file_description,
                    'file_is_premium'  => (int) $r->file_is_premium,
                    'allow_public_view'=> $r->allow_public_view,
                    'display_order'    => (int) $r->display_order,
                    // URLs الفعلية تُقدَّم من خدمة الملفات عند الطلب
                ];
                $out['stats']['total_files']++;
            }
        }
        foreach ($weeks as &$w) {
            $w['tasks'] = array_values($w['tasks']);
        }
        usort($weeks, fn($a,$b)=>$a['week_number']<=>$b['week_number']);
        $out['weeks'] = array_values($weeks);

        return $this->j(['success'=>true,'data'=>$out]);
    }

    /* =========================================================
     * 3) إحصاءات المساق
     *    GET /api/student/courses/{co}/stats
     * =======================================================*/
    public function courseStats(Request $request, int $courseOfferingId)
    {
        $uid = $this->userId($request);
        if (!$this->canAccessCourse($uid, $courseOfferingId)) {
            return $this->j(['success'=>false,'message'=>'Access denied'], 403);
        }

        $tz        = new \DateTimeZone('Asia/Amman');
        $today     = (new \DateTimeImmutable('today', $tz));
        $next7     = $today->modify('+7 days');

        $sql = "
        WITH student AS (
            SELECT du.user_id, du.university_id, du.specialty_id, du.academic_year_id
            FROM diploma_users du WHERE du.user_id = ?
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
        LEFT JOIN tasks t            ON t.weekly_content_id = wc.id
        LEFT JOIN task_files tf      ON tf.task_id = t.id
        LEFT JOIN files f            ON f.id = tf.file_id
        WHERE co.id = ?";
        $row = DB::connection('diploma')->selectOne($sql, [
            $uid,
            $today->format('Y-m-d'),
            $today->format('Y-m-d'), $next7->format('Y-m-d'),
            $next7->format('Y-m-d'),
            $courseOfferingId
        ]) ?: (object)[];

        return $this->j(['success'=>true,'data'=>[
            'total_weeks'    => (int) ($row->total_weeks    ?? 0),
            'total_tasks'    => (int) ($row->total_tasks    ?? 0),
            'total_files'    => (int) ($row->total_files    ?? 0),
            'available_weeks'=> (int) ($row->available_weeks?? 0),
            'upcoming_weeks' => (int) ($row->upcoming_weeks ?? 0),
            'locked_weeks'   => (int) ($row->locked_weeks   ?? 0),
        ]]);
    }

    /* =========================================================
     * 4) البحث داخل المساق
     *    GET /api/student/courses/{co}/search?q=...
     * =======================================================*/
    public function searchInCourse(Request $request, int $courseOfferingId)
    {
        $uid = $this->userId($request);
        $q   = trim((string) $request->query('q',''));
        if (mb_strlen($q) < 2) {
            return $this->j(['success'=>false,'message'=>'Search query too short'], 422);
        }
        if (!$this->canAccessCourse($uid, $courseOfferingId)) {
            return $this->j(['success'=>false,'message'=>'Access denied'], 403);
        }

        $spec = DB::connection('diploma')->table('diploma_users')
            ->where('user_id',$uid)->value('specialty_id');
        $stIsNull = is_null($spec) ? 1 : 0;
        $pattern  = "%{$q}%";

        $nowTs = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Amman')))->format('Y-m-d H:i:s');

        $sql = "
        WITH st AS ( SELECT ? AS specialty_id, ? AS st_is_null )
        SELECT * FROM (
            SELECT 'week' AS type, wc.id AS id, wc.title AS title, wc.week_number, NULL AS task_type
            FROM weekly_contents wc
            WHERE wc.course_offering_id = ? AND wc.title LIKE ?

            UNION ALL

            SELECT 'task' AS type, t.id AS id, t.title AS title, wc.week_number, tt.name AS task_type
            FROM tasks t
            JOIN weekly_contents wc ON wc.id = t.weekly_content_id
            LEFT JOIN task_types tt ON tt.id = t.task_type_id
            JOIN st ON 1
            WHERE wc.course_offering_id = ?
              AND (t.title LIKE ? OR t.description LIKE ?)
              AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
              AND (
                    COALESCE(t.is_for_all_specialties,0) = 1
                 OR st.st_is_null = 1
                 OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
                 OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id)
              )
        ) q
        ORDER BY q.week_number, q.type";
        $rows = DB::connection('diploma')->select($sql, [
            $spec, $stIsNull,
            $courseOfferingId, $pattern,
            $courseOfferingId, $pattern, $pattern, $nowTs
        ]);

        return $this->j(['success'=>true,'data'=>$rows, 'query'=>$q]);
    }

    /* =========================================================
     * 5) سياق مهمة للتفاصيل
     *    GET /api/student/tasks/{taskId}/context
     * =======================================================*/
    public function taskContext(Request $request, int $taskId)
    {
        $uid = $this->userId($request);

        // رأس المهمة + الأسبوع + المساق (مع تحقق الوصول)
        $nowTs = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Amman')))->format('Y-m-d H:i:s');

        $hdrSql = "
        WITH st AS (
            SELECT du.user_id, du.university_id, du.academic_year_id, du.specialty_id
            FROM diploma_users du WHERE du.user_id = ?
        )
        SELECT
            t.id AS task_id, t.title AS task_title, t.description AS task_description,
            t.visible_from_date, t.due_date, t.is_for_all_specialties,
            tt.name AS task_type_name,
            wc.id AS weekly_content_id, wc.week_number, wc.title AS week_title, wc.publish_date,
            co.id AS course_offering_id, c.id AS course_id, c.name AS course_name, c.code AS course_code
        FROM st
        JOIN semesters s         ON s.academic_year_id = st.academic_year_id
        JOIN course_offerings co ON co.semester_id = s.id AND co.university_id = st.university_id
        JOIN weekly_contents wc  ON wc.course_offering_id = co.id
        JOIN tasks t             ON t.weekly_content_id = wc.id
        LEFT JOIN task_types tt  ON tt.id = t.task_type_id
        JOIN courses c           ON c.id = co.course_id
        WHERE t.id = ?
          AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
          AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR st.specialty_id IS NULL
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id)
          )
        LIMIT 1";
        $hdr = DB::connection('diploma')->selectOne($hdrSql, [$uid, $taskId, $nowTs]);
        if (!$hdr) return $this->j(['success'=>false,'message'=>'Task not found'], 404);

        // قائمة مهام نفس الأسبوع (للـ prev/next)
        $sibSql = "
        WITH st AS ( SELECT du.user_id, du.specialty_id FROM diploma_users du WHERE du.user_id = ? )
        SELECT t.id AS task_id, t.title AS task_title, tt.name AS task_type_name
        FROM tasks t
        LEFT JOIN task_types tt ON tt.id = t.task_type_id
        JOIN st ON 1
        WHERE t.weekly_content_id = ?
          AND (t.visible_from_date IS NULL OR t.visible_from_date <= ?)
          AND (
                COALESCE(t.is_for_all_specialties,0) = 1
             OR st.specialty_id IS NULL
             OR NOT EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id)
             OR EXISTS (SELECT 1 FROM task_specialties ts WHERE ts.task_id = t.id AND ts.specialty_id = st.specialty_id)
          )
        ORDER BY t.id";
        $siblings = DB::connection('diploma')->select($sibSql, [$uid, (int)$hdr->weekly_content_id, $nowTs]);

        $idx=null; $prev=null; $next=null;
        foreach ($siblings as $i => $row) {
            if ((int)$row->task_id === (int)$taskId) {
                $idx  = $i;
                $prev = $siblings[$i-1]->task_id ?? null;
                $next = $siblings[$i+1]->task_id ?? null;
                break;
            }
        }

        // ملفات المهمة
        $files = DB::connection('diploma')->table('task_files as tf')
            ->join('files as f','f.id','=','tf.file_id')
            ->where('tf.task_id',$taskId)
            ->orderBy('tf.display_order')->orderBy('f.id')
            ->get([
                'f.id as file_id','f.file_name','f.mime_type','f.file_size_bytes',
                'f.description as file_description',
                DB::raw('COALESCE(f.is_premium,0) as file_is_premium'),
                'f.allow_public_view'
            ])->map(function($f){
                $mt = strtolower((string)$f->mime_type);
                return [
                    'file_id'          => (int) $f->file_id,
                    'file_name'        => $f->file_name,
                    'mime_type'        => $f->mime_type,
                    'file_size_bytes'  => $f->file_size_bytes,
                    'file_description' => $f->file_description,
                    'file_is_premium'  => (int) $f->file_is_premium,
                    'allow_public_view'=> $f->allow_public_view,
                    'is_media'         => (int) (str_starts_with($mt,'image/') || str_starts_with($mt,'video/') || str_starts_with($mt,'audio/')),
                    'is_pdf'           => (int) ($mt === 'application/pdf'),
                    // روابط البث/التحميل تُقدَّم من خدمة الملفات (API) كما في منصتك
                    'stream_url'       => url('api/files/'.$f->file_id.'/stream'),
                    'download_url'     => url('api/files/'.$f->file_id.'/download'),
                    'check_url'        => url('api/files/'.$f->file_id.'/check?action=download'),
                ];
            })->values()->all();

        return $this->j(['success'=>true,'data'=>[
            'course'=>[
                'course_offering_id'=>(int)$hdr->course_offering_id,
                'course_id'         =>(int)$hdr->course_id,
                'course_name'       =>$hdr->course_name,
                'course_code'       =>$hdr->course_code,
            ],
            'week'=>[
                'weekly_content_id'=>(int)$hdr->weekly_content_id,
                'week_number'      =>(int)$hdr->week_number,
                'week_title'       =>$hdr->week_title,
                'publish_date'     =>$hdr->publish_date,
            ],
            'task'=>[
                'task_id'              =>(int)$hdr->task_id,
                'task_title'           =>$hdr->task_title,
                'task_description'     =>$hdr->task_description,
                'task_type_name'       =>$hdr->task_type_name,
                'visible_from_date'    =>$hdr->visible_from_date,
                'due_date'             =>$hdr->due_date,
                'is_for_all_specialties'=>$hdr->is_for_all_specialties,
                'files'                =>$files,
            ],
            'siblings'=>[
                'index'        =>$idx,
                'prev_task_id' =>$prev ? (int)$prev : null,
                'next_task_id' =>$next ? (int)$next : null,
                'list'         =>$siblings,
            ],
        ]]);
    }

    /* =========================================================
     * 6) تحديث آخر وصول للمساق (اختياري)
     *    POST /api/student/courses/{co}/touch
     * =======================================================*/
    public function updateLastAccess(Request $request, int $courseOfferingId)
    {
        $uid = $this->userId($request);
        if (!$this->canAccessCourse($uid, $courseOfferingId)) {
            return $this->j(['success'=>false], 403);
        }
        // سجّل كما يلزمك (DB/Cache/Logs). هنا نُعيد ok فقط.
        return $this->j(['success'=>true]);
    }
}
