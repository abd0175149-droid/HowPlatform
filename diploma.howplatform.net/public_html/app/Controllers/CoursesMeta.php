<?php

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\UniversityModel;
use App\Models\SemesterModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\CourseModel;
use App\Models\CourseOfferingModel;
use CodeIgniter\Controller;
class CoursesMeta extends Controller
{
 public function index()
    {
        $model = new AcademicYearModel();
        $academicYears = $model->select('id, label')
                               ->orderBy('start_date', 'DESC')
                               ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'academic_years' => $academicYears,
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
            'universities' => $universities,
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
            'semesters' => $semesters,
        ]);
    }

    public function store()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'طلب غير مصرح به.',
            ]);
        }

        $data = json_decode($this->request->getBody(), true);

        if (! $data || !isset($data['name'], $data['code'], $data['academic_year_id'], $data['university_id'], $data['semester_id'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'بيانات غير مكتملة.',
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $courseModel = new CourseModel();
        $courseModel->insert([
            'name'        => $data['name'],
            'code'        => $data['code'],
            'description' => $data['description'] ?? null
        ]);
        $courseId = $courseModel->getInsertID();

        $offeringModel = new CourseOfferingModel();
        $offeringModel->insert([
            'course_id'     => $courseId,
            'semester_id'   => $data['semester_id'],
            'university_id' => $data['university_id']
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'فشل في إضافة المساق. يرجى المحاولة لاحقًا.'
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم إضافة المساق بنجاح.'
        ]);
    }

    public function getCoursesByFilters()
    {
        $academicYearId = $this->request->getGet('academic_year_id');
        $universityId   = $this->request->getGet('university_id');
        $semesterId     = $this->request->getGet('semester_id');

        if (!$academicYearId || !$universityId || !$semesterId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'البيانات المطلوبة غير مكتملة.'
            ]);
        }

        $db = \Config\Database::connect();

        $results = $db->query("
            SELECT 
                courses.id,
                courses.name,
                courses.code,
                universities.name AS university_name,
                COUNT(DISTINCT weekly_contents.id) AS weeks_count
            FROM course_offerings
            JOIN courses ON courses.id = course_offerings.course_id
            JOIN universities ON universities.id = course_offerings.university_id
            JOIN semesters ON semesters.id = course_offerings.semester_id
            LEFT JOIN weekly_contents ON weekly_contents.course_offering_id = course_offerings.id
            WHERE semesters.academic_year_id = ?
              AND course_offerings.university_id = ?
              AND course_offerings.semester_id = ?
            GROUP BY courses.id
        ", [$academicYearId, $universityId, $semesterId])->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'courses' => $results
        ]);
    }

    public function show($id)
    {
        $courseModel = new CourseModel();
        $course = $courseModel->find($id);

        if (! $course) {
            return $this->response->setJSON(['success' => false, 'message' => 'المساق غير موجود.']);
        }

        $offeringModel = new CourseOfferingModel();
        $offering = $offeringModel->where('course_id', $id)->first();

        $semesterModel = new SemesterModel();
        $semester = $semesterModel->find($offering['semester_id']);

        $universityModel = new UniversityModel();
        $university = $universityModel->find($offering['university_id']);

        $yearModel = new AcademicYearModel();
        $year = $yearModel->find($semester['academic_year_id']);

        return $this->response->setJSON([
            'success' => true,
            'course' => [
                'id' => $course['id'],
                'name' => $course['name'],
                'code' => $course['code'],
                'description' => $course['description'],
                'academic_year_label' => $year['label'],
                'university_name' => $university['name'],
                'semester_name' => $semester['name']
            ]
        ]);
    }

    public function update($id)
    {
        $data = json_decode($this->request->getBody(), true);

        if (! $data || !isset($data['name'], $data['code'], $data['description'], $data['university_id'], $data['semester_id'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات غير مكتملة.']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $courseModel = new CourseModel();
        $courseModel->update($id, [
            'name'        => $data['name'],
            'code'        => $data['code'],
            'description' => $data['description']
        ]);

        $offeringModel = new CourseOfferingModel();
        $offeringModel->where('course_id', $id)->delete();

        $offeringModel->insert([
            'course_id'     => $id,
            'semester_id'   => $data['semester_id'],
            'university_id' => $data['university_id']
        ]);

        $db->transComplete();

        return $this->response->setJSON(['success' => true, 'message' => 'تم تحديث المساق بنجاح.']);
    }

    public function delete($id)
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'طلب غير مصرح به.'
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $offeringModel = new CourseOfferingModel();
        $offeringModel->where('course_id', $id)->delete();

        $courseModel = new CourseModel();
        $courseModel->delete($id);

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف.'
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم حذف المساق بنجاح.'
        ]);
    }
}
