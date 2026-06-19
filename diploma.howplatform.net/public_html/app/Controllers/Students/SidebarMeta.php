<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Helpers\StudentViewHelper;
use App\Libraries\JwtHelper;
use CodeIgniter\Controller;

/**
 * يُرجع بيانات السايدبار (DDL للفصول + المساقات) بصيغة JSON.
 * يعتمد على diploma_users ويستخدم كاش خادمي اختياري مع ?refresh=1 لتجاوز الكاش.
 */
class SidebarMeta extends Controller
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    /** @var \CodeIgniter\Cache\CacheInterface */
    protected $cache;

    private const CACHE_TTL = 600; // ⏱️ 10 دقائق

    public function __construct()
    {
        $this->db    = \Config\Database::connect();
        $this->cache = \Config\Services::cache();
    }

    public function index()
    {
        // 🧩 قراءة المستخدم من JWT/Session/Cookie عبر الهيلبر
        $userId = StudentViewHelper::getEffectiveUserId() ?? JwtHelper::currentUserId();
        if (!$userId) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // استخدام الإعدادات الفعالة
        $universityId = StudentViewHelper::getEffectiveUniversityId();
        $academicYearId = StudentViewHelper::getEffectiveAcademicYearId();

        if (!$universityId || !$academicYearId) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['status' => 'error', 'message' => 'الرجاء تحديد الجامعة والسنة الأكاديمية']);
        }

        // 👇 دعم كسر الكاش بـ refresh=1
        $force   = $this->request->getGet('refresh') === '1';
        $ck      = "sidebar_meta_{$userId}_{$universityId}_{$academicYearId}";
        $fromCache = false;

        if (!$force) {
            $cached = $this->cache->get($ck);
            if ($cached !== null) {
                $fromCache = true;
                return $this->jsonNoStore(['status' => 'ok', 'from_cache' => true] + $cached);
            }
        }

        // 🗄️ Query: اجلب الفصول لمسار الأكاديمي + العروض للمساقات لجامعة الطالب
        $rows = $this->db->query("
            WITH st AS (
                SELECT ? AS user_id, ? AS university_id, ? AS academic_year_id
            )
            SELECT 
                s.id   AS semester_id,
                s.name AS semester_name,
                co.id  AS offering_id,
                c.id   AS course_id,
                c.name AS course_name,
                c.code AS course_code
            FROM st
            JOIN semesters s         ON s.academic_year_id = st.academic_year_id
            JOIN course_offerings co ON co.semester_id     = s.id 
                                    AND co.university_id   = st.university_id
            JOIN courses c           ON c.id               = co.course_id
            ORDER BY s.id, c.name
        ", [$userId, $universityId, $academicYearId])->getResultArray();

        // 🧱 تنظيم الناتج بصيغة:
        // { semesters: [ { semester: {id,name}, courses: [ {offering_id,course_id,name,code} ] } ] }
        $bySem = [];
        foreach ($rows as $r) {
            $sid = (int) $r['semester_id'];
            if (!isset($bySem[$sid])) {
                $bySem[$sid] = [
                    'semester' => ['id' => $sid, 'name' => (string) $r['semester_name']],
                    'courses'  => [],
                ];
            }
            $bySem[$sid]['courses'][] = [
                'offering_id' => (int) $r['offering_id'],
                'course_id'   => (int) $r['course_id'],
                'name'        => (string) $r['course_name'],
                'code'        => (string) $r['course_code'],
            ];
        }

        $payload = ['semesters' => array_values($bySem)];

        // 💾 خزّن نسخة بالكاش الخادمي
        $this->cache->save($ck, $payload, self::CACHE_TTL);

        return $this->jsonNoStore(['status' => 'ok', 'from_cache' => $fromCache] + $payload);
    }

    /**
     * رد JSON مع هِدرات عدم التخزين للمتصفح/الوسيط.
     */
    private function jsonNoStore(array $data)
    {
        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setJSON($data);
    }
}
