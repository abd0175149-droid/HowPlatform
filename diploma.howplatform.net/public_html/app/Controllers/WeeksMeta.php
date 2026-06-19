<?php
namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\UniversityModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\SemesterModel;
use App\Models\CourseModel;
use App\Models\CourseOfferingModel;
use CodeIgniter\Controller;

class WeeksMeta extends Controller
{
    public function index()
    {
        $model = new AcademicYearModel();
        $years = $model->orderBy('start_date', 'DESC')->findAll();

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
            ->where('university_academic_years.academic_year_id', $academicYearId)
            ->groupBy('universities.id')
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
                           ->where('academic_year_id', $academicYearId)
                           ->orderBy('id', 'ASC')
                           ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'semesters' => $semesters
        ]);
    }

    public function getCourses()
    {
        $req = $this->request;
        $academicYearId = $req->getGet('academic_year_id');
        $universityId   = $req->getGet('university_id');
        $semesterId     = $req->getGet('semester_id');

        if (! $academicYearId || ! $universityId || ! $semesterId) {
            return $this->response->setJSON([
                'success' => false,
                'courses' => []
            ]);
        }

        $model = new CourseOfferingModel();
        $builder = $model->builder();

        $courses = $builder->select('co.course_id, c.name')
            ->from('course_offerings co')
            ->join('courses c', 'c.id = co.course_id')
            ->join('semesters s', 's.id = co.semester_id')
            ->where('co.university_id', $universityId)
            ->where('co.semester_id', $semesterId)
            ->where('s.academic_year_id', $academicYearId)
            ->groupBy('co.course_id')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'courses' => $courses
        ]);
    }

    public function store()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['course_id'], $data['week_number'], $data['title'], $data['publish_date'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'البيانات غير مكتملة'
            ]);
        }

        $offeringModel = new CourseOfferingModel();
        $offerings = $offeringModel->select('id')
                                   ->where('course_id', $data['course_id'])
                                   ->findAll();

        if (empty($offerings)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'لا توجد عروض مرتبطة بهذا المساق.'
            ]);
        }

        $builder = db_connect()->table('weekly_contents');
        foreach ($offerings as $offering) {
            $builder->insert([
                'course_offering_id' => $offering['id'],
                'week_number'        => $data['week_number'],
                'title'              => $data['title'],
                'publish_date'       => $data['publish_date']
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تمت إضافة الأسبوع لجميع العروض المرتبطة بالمساق.'
        ]);
    }

    public function show($id)
    {
        $db = db_connect();

        $week = $db->table('weekly_contents wc')
            ->select('wc.id, wc.week_number, wc.title, wc.publish_date,
          ay.label  as academic_year,
          u.name    as university,
          s.name    as semester,
          c.name    as course')

            ->join('course_offerings co', 'co.id = wc.course_offering_id')
            ->join('courses c', 'c.id = co.course_id')
            ->join('semesters s', 's.id = co.semester_id')
            ->join('universities u', 'u.id = co.university_id')
            ->join('academic_years ay', 'ay.id = s.academic_year_id')
            ->where('wc.id', $id)
            ->get()
            ->getRowArray();

        if (! $week) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'الأسبوع غير موجود'
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'week' => $week
        ]);
    }

    public function update($id)
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['week_number'], $data['title'], $data['publish_date'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'البيانات غير مكتملة'
            ]);
        }

        $builder = db_connect()->table('weekly_contents');
        $builder->where('id', $id)->update([
            'week_number'  => $data['week_number'],
            'title'        => $data['title'],
            'publish_date' => $data['publish_date']
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم تحديث الأسبوع بنجاح'
        ]);
    }

    public function delete($id)
    {
        $deleted = db_connect()->table('weekly_contents')
            ->where('id', $id)->delete();

        return $this->response->setJSON([
            'success' => $deleted,
            'message' => $deleted ? 'تم حذف الأسبوع بنجاح' : 'فشل في حذف الأسبوع'
        ]);
    }

    public function list()
    {
        $req = $this->request;

        $academicYearId = $req->getGet('academic_year_id');
        $universityId   = $req->getGet('university_id');
        $semesterId     = $req->getGet('semester_id');
        $courseId       = $req->getGet('course_id');
        $page           = (int) $req->getGet('page') ?: 1;
        $limit          = (int) $req->getGet('limit') ?: 10;
        $offset         = ($page - 1) * $limit;

        if (! $academicYearId || ! $universityId || ! $semesterId || ! $courseId) {
            return $this->response->setJSON([
                'success' => false,
                'weeks'   => [],
                'message' => 'يجب اختيار السنة والجامعة والفصل والمساق'
            ]);
        }

        $db = db_connect();

        // 1. عدد الصفوف
        $total = $db->table('weekly_contents wc')
            ->join('course_offerings co', 'co.id = wc.course_offering_id')
            ->join('semesters se', 'se.id = co.semester_id')
            ->where('co.university_id', $universityId)
            ->where('co.semester_id', $semesterId)
            ->where('co.course_id', $courseId)
            ->where('se.academic_year_id', $academicYearId)
            ->countAllResults(false);

        // 2. البيانات
        $builder = $db->table('weekly_contents wc');
        $builder->select('wc.id, c.name as course_name, wc.title, COUNT(t.id) as tasks_count');
        $builder->join('course_offerings co', 'co.id = wc.course_offering_id');
        $builder->join('courses c', 'c.id = co.course_id');
        $builder->join('semesters se', 'se.id = co.semester_id');
        $builder->join('tasks t', 't.weekly_content_id = wc.id', 'left');
        $builder->where('co.university_id', $universityId);
        $builder->where('co.semester_id', $semesterId);
        $builder->where('co.course_id', $courseId);
        $builder->where('se.academic_year_id', $academicYearId);
        $builder->groupBy('wc.id');
        $builder->limit($limit, $offset);

        $weeks = $builder->get()->getResultArray();

        return $this->response->setJSON([
            'success'      => true,
            'weeks'        => $weeks,
            'total'        => $total,
            'current_page' => $page,
            'limit'        => $limit,
            'last_page'    => ceil($total / $limit)
        ]);
    }
}
