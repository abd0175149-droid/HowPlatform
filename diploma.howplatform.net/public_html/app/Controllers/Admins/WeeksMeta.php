<?php
namespace App\Controllers\Admins;

use App\Models\AcademicYearModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\SemesterModel;
use App\Models\CourseOfferingModel;
use CodeIgniter\Controller;
use CodeIgniter\Database\Exceptions\DatabaseException;

class WeeksMeta extends Controller
{
    /* ==========================
       Helpers
    ========================== */

    protected function readPayload(): array
    {
        // 1) JSON
        $data = $this->request->getJSON(true);
        if (is_array($data) && !empty($data)) return $data;

        // 2) Raw body → JSON
        $raw = $this->request->getBody();
        if ($raw) {
            $tmp = json_decode($raw, true);
            if (is_array($tmp) && !empty($tmp)) return $tmp;
        }

        // 3) x-www-form-urlencoded
        $post = $this->request->getPost(null, FILTER_UNSAFE_RAW);
        return is_array($post) ? $post : [];
    }

    protected function normalizeDate(?string $date): ?string
    {
        if (!$date) return null;
        // قبول صيغ مختلفة وتحويلها إلى Y-m-d
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /* ==========================
       Meta (Years / Universities / Semesters / Courses)
    ========================== */

    public function index()
    {
        $model = new AcademicYearModel();
        $years = $model->select('id, label, start_date')
                       ->orderBy('start_date', 'DESC')
                       ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'academic_years' => $years
        ]);
    }

    public function getUniversities($academicYearId)
    {
        $model = new UniversityAcademicYearModel();
        $builder = $model->builder();

        $universities = $builder->select('universities.id, universities.name')
            ->join('universities', 'universities.id = university_academic_years.university_id')
            ->where('university_academic_years.academic_year_id', (int)$academicYearId)
            ->groupBy('universities.id, universities.name')
            ->orderBy('universities.name', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'universities' => $universities
        ]);
    }

    public function getSemesters($academicYearId)
    {
        $model = new SemesterModel();
        $semesters = $model->select('id, name')
                           ->where('academic_year_id', (int)$academicYearId)
                           ->orderBy('id', 'ASC')
                           ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'semesters' => $semesters
        ]);
    }

    /**
     * GET /admins/api/weeks/courses?academic_year_id=&university_id=&semester_id=
     */
    public function getCourses()
    {
        $req = $this->request;
        $academicYearId = (int) $req->getGet('academic_year_id');
        $universityId   = (int) $req->getGet('university_id');
        $semesterId     = (int) $req->getGet('semester_id');

        if (!$academicYearId || !$universityId || !$semesterId) {
            return $this->response->setJSON([
                'success' => false,
                'courses' => [],
                'message' => 'يلزم العام والجامعة والفصل.'
            ]);
        }

        $db = db_connect();
        $courses = $db->table('course_offerings co')
            ->select('co.course_id, c.name')
            ->join('courses c', 'c.id = co.course_id')
            ->join('semesters s', 's.id = co.semester_id')
            ->where('co.university_id', $universityId)
            ->where('co.semester_id', $semesterId)
            ->where('s.academic_year_id', $academicYearId)
            ->groupBy('co.course_id, c.name')
            ->orderBy('c.name', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'courses' => $courses
        ]);
    }

    /* ==========================
       CRUD: Weeks (weekly_contents)
    ========================== */

    /**
     * store()
     * يدعم:
     *   A) دفعة متعددة: {
     *        academic_year_id, semester_id, week_number, title, publish_date,
     *        targets: [{university_id, course_ids:[..]}, ...]
     *      }
     *   B) أسلوب قديم: { course_id, week_number, title, publish_date, [university_id], [semester_id] }
     */
    public function store()
    {
        $data = $this->readPayload();
        log_message('debug', 'WeeksMeta::store payload: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $db = db_connect();
        $db->transException(true); // يرمي استثناء عند فشل الترانزاكشن

        try {
            // ===== نمط A: دفعة متعددة
            if (isset($data['targets']) && is_array($data['targets']) && !empty($data['targets'])) {
                $weekNumber     = (int)($data['week_number'] ?? 0);
                $title          = trim((string)($data['title'] ?? ''));
                $publishDateRaw = (string)($data['publish_date'] ?? '');
                $publishDate    = $this->normalizeDate($publishDateRaw);
                $yearId         = (int)($data['academic_year_id'] ?? 0);
                $semesterId     = (int)($data['semester_id'] ?? 0);

                if ($weekNumber <= 0 || $title === '' || !$publishDate || !$yearId || !$semesterId) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'message' => 'البيانات غير مكتملة (دفعة متعددة).'
                    ]);
                }

                $offeringModel = new CourseOfferingModel();
                $created = [];
                $skipped = [];

                $db->transStart();

                foreach ($data['targets'] as $row) {
                    $uniId     = (int)($row['university_id'] ?? 0);
                    $courseIds = $row['course_ids'] ?? [];
                    if (!$uniId || !is_array($courseIds) || empty($courseIds)) {
                        $skipped[] = ['reason' => 'بيانات صف ناقصة', 'row' => $row];
                        continue;
                    }

                    foreach ($courseIds as $cid) {
                        $cid = (int)$cid;
                        if ($cid <= 0) {
                            $skipped[] = ['reason' => 'course_id غير صالح', 'row' => $row];
                            continue;
                        }

                        $offering = $offeringModel->where([
                                'course_id'     => $cid,
                                'university_id' => $uniId,
                                'semester_id'   => $semesterId,
                            ])->first();

                        if (!$offering) {
                            $skipped[] = [
                                'reason'        => 'لا يوجد عرض (offering) لهذا المساق في هذه الجامعة/الفصل',
                                'university_id' => $uniId,
                                'course_id'     => $cid,
                                'semester_id'   => $semesterId
                            ];
                            continue;
                        }

                        $db->table('weekly_contents')->insert([
                            'course_offering_id' => (int)$offering['id'],
                            'week_number'        => $weekNumber,
                            'title'              => $title,
                            'publish_date'       => $publishDate
                        ]);

                        $created[] = [
                            'offering_id'  => (int)$offering['id'],
                            'university_id'=> (int)$offering['university_id'],
                            'course_id'    => (int)$offering['course_id'],
                            'semester_id'  => (int)$offering['semester_id'],
                        ];
                    }
                }

                $db->transComplete();

                return $this->response->setJSON([
                    'success'       => true,
                    'message'       => 'تم حفظ الأسبوع بنجاح.',
                    'created_count' => count($created),
                    'skipped_count' => count($skipped),
                    'created'       => $created,
                    'skipped'       => $skipped
                ]);
            }

            // ===== نمط B: قديم (course_id واحد) — هذا ما يُظهره لوجك الآن
            $hasRequired = isset($data['course_id'], $data['week_number'], $data['title'], $data['publish_date']);
            if (!$hasRequired) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'البيانات غير مكتملة.'
                ]);
            }

            $courseId      = (int)$data['course_id'];
            $weekNumber    = (int)$data['week_number'];
            $title         = trim((string)$data['title']);
            $publishDate   = $this->normalizeDate((string)$data['publish_date']);
            $filterUniId   = isset($data['university_id']) ? (int)$data['university_id'] : null;
            $filterSemId   = isset($data['semester_id'])   ? (int)$data['semester_id']   : null;

            if ($courseId <= 0 || $weekNumber <= 0 || $title === '' || !$publishDate) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'القيم غير صالحة.'
                ]);
            }

            // ابحث عن كل العروض الخاصة بهذا المساق (مع القيود الاختيارية)
            $offeringModel = new CourseOfferingModel();
            $offeringModel->where('course_id', $courseId);
            if ($filterUniId) $offeringModel->where('university_id', $filterUniId);
            if ($filterSemId) $offeringModel->where('semester_id',   $filterSemId);
            $offerings = $offeringModel->select('id, university_id, semester_id, course_id')->findAll();

            if (empty($offerings)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'لا توجد عروض مرتبطة بهذا المساق.'
                ]);
            }

            $db->transStart();
            foreach ($offerings as $off) {
                $db->table('weekly_contents')->insert([
                    'course_offering_id' => (int)$off['id'],
                    'week_number'        => $weekNumber,
                    'title'              => $title,
                    'publish_date'       => $publishDate
                ]);
            }
            $db->transComplete();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'تمت إضافة الأسبوع لجميع العروض المرتبطة بالمساق.'
            ]);

        } catch (DatabaseException $e) {
            log_message('error', 'WeeksMeta::store DB error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'خطأ داخلي في قاعدة البيانات.',
                'error'   => ENVIRONMENT !== 'production' ? $e->getMessage() : null
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'WeeksMeta::store error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع.',
                'error'   => ENVIRONMENT !== 'production' ? $e->getMessage() : null
            ]);
        }
    }

    /**
     * GET /admins/api/weeks/show/{id}
     * إرجاع تفاصيل أسبوع واحد (تُستخدم في مودال التعديل)
     */
    public function show($id)
    {
        $db = db_connect();

        $week = $db->table('weekly_contents wc')
            ->select('
                wc.id, wc.week_number, wc.title, wc.publish_date,
                ay.label  as academic_year,
                u.id      as university_id,
                u.name    as university,
                s.id      as semester_id,
                s.name    as semester,
                c.id      as course_id,
                c.name    as course
            ')
            ->join('course_offerings co', 'co.id = wc.course_offering_id')
            ->join('courses c', 'c.id = co.course_id')
            ->join('semesters s', 's.id = co.semester_id')
            ->join('universities u', 'u.id = co.university_id')
            ->join('academic_years ay', 'ay.id = s.academic_year_id')
            ->where('wc.id', (int)$id)
            ->get()
            ->getRowArray();

        if (!$week) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'الأسبوع غير موجود'
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'week'    => $week
        ]);
    }

    /**
     * PUT /admins/api/weeks/update/{id}
     */
    public function update($id)
    {
        $data = $this->readPayload();

        if (!isset($data['week_number'], $data['title'], $data['publish_date'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'البيانات غير مكتملة'
            ]);
        }

        $weekNumber  = (int)$data['week_number'];
        $title       = trim((string)$data['title']);
        $publishDate = $this->normalizeDate((string)$data['publish_date']);

        if ($weekNumber <= 0 || $title === '' || !$publishDate) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'القيم غير صالحة'
            ]);
        }

        try {
            $updated = db_connect()->table('weekly_contents')
                ->where('id', (int)$id)
                ->update([
                    'week_number'  => $weekNumber,
                    'title'        => $title,
                    'publish_date' => $publishDate
                ]);

            return $this->response->setJSON([
                'success' => (bool)$updated,
                'message' => $updated ? 'تم تحديث الأسبوع بنجاح' : 'فشل في تحديث الأسبوع'
            ]);
        } catch (DatabaseException $e) {
            log_message('error', 'WeeksMeta::update DB error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'خطأ داخلي في قاعدة البيانات.'
            ]);
        }
    }

    /**
     * DELETE /admins/api/weeks/delete/{id}
     */
    public function delete($id)
    {
        try {
            $deleted = db_connect()->table('weekly_contents')
                ->where('id', (int)$id)
                ->delete();

            return $this->response->setJSON([
                'success' => (bool)$deleted,
                'message' => $deleted ? 'تم حذف الأسبوع بنجاح' : 'فشل في حذف الأسبوع'
            ]);
        } catch (DatabaseException $e) {
            log_message('error', 'WeeksMeta::delete DB error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'خطأ داخلي في قاعدة البيانات.'
            ]);
        }
    }

    /**
     * GET /admins/api/weeks/list?academic_year_id=&university_id=&semester_id=&[course_id]&[sort=num|course|tasks]&[dir=asc|desc]&[page]&[limit]
     * لو لم يُحدَّد course_id سيتم عرض جميع الأسابيع لكل المساقات ضمن العام/الجامعة/الفصل.
     */
    public function list()
    {
        $req = $this->request;

        $academicYearId = (int)$req->getGet('academic_year_id');
        $universityId   = (int)$req->getGet('university_id');
        $semesterId     = (int)$req->getGet('semester_id');
        $courseId       = (int)$req->getGet('course_id');  // اختياري
        $page           = max(1, (int)$req->getGet('page'));
        $limit          = max(1, (int)$req->getGet('limit') ?: 10);
        $offset         = ($page - 1) * $limit;

        $sort = $req->getGet('sort'); // num | course | tasks
        $dir  = strtolower($req->getGet('dir') ?? 'asc');
        $dir  = in_array($dir, ['asc', 'desc']) ? $dir : 'asc';

        if (!$academicYearId || !$universityId || !$semesterId) {
            return $this->response->setJSON([
                'success' => false,
                'weeks'   => [],
                'message' => 'يجب اختيار السنة والجامعة والفصل.'
            ]);
        }

        $db = db_connect();

        // ===== Count
        $cnt = $db->table('weekly_contents wc')
            ->join('course_offerings co', 'co.id = wc.course_offering_id')
            ->join('semesters se', 'se.id = co.semester_id')
            ->where('co.university_id', $universityId)
            ->where('co.semester_id', $semesterId)
            ->where('se.academic_year_id', $academicYearId);

        if ($courseId) $cnt->where('co.course_id', $courseId);
        $total = $cnt->countAllResults();

        // ===== Data
        $builder = $db->table('weekly_contents wc');
        $builder->select('
            wc.id,
            wc.week_number,
            wc.title,
            wc.publish_date,
            c.name as course_name,
            COUNT(t.id) as tasks_count
        ');
        $builder->join('course_offerings co', 'co.id = wc.course_offering_id');
        $builder->join('courses c', 'c.id = co.course_id');
        $builder->join('semesters se', 'se.id = co.semester_id');
        $builder->join('tasks t', 't.weekly_content_id = wc.id', 'left');
        $builder->where('co.university_id', $universityId);
        $builder->where('co.semester_id', $semesterId);
        $builder->where('se.academic_year_id', $academicYearId);
        if ($courseId) $builder->where('co.course_id', $courseId);
        $builder->groupBy('wc.id, wc.week_number, wc.title, wc.publish_date, c.name');

        switch ($sort) {
            case 'course': $builder->orderBy('c.name', $dir); break;
            case 'tasks' : $builder->orderBy('tasks_count', $dir, false); break;
            case 'num'   :
            default      : $builder->orderBy('wc.week_number', $dir);
        }

        $builder->limit($limit, $offset);
        $weeks = $builder->get()->getResultArray();

        return $this->response->setJSON([
            'success'      => true,
            'weeks'        => $weeks,
            'total'        => (int)$total,
            'current_page' => (int)$page,
            'limit'        => (int)$limit,
            'last_page'    => (int)ceil($total / $limit)
        ]);
    }
}
