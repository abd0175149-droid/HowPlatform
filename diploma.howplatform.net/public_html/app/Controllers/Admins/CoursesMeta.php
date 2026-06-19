<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Models\AcademicYearModel;
use App\Models\UniversityModel;
use App\Models\SemesterModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\CourseModel;
use App\Models\CourseOfferingModel;
use CodeIgniter\Controller;

class CoursesMeta extends Controller
{
    /**
     * GET /admins/api/courses/meta
     * يعيد الأعوام الدراسية (id, label) بترتيب تنازلي حسب start_date
     */
    public function index()
    {
        $model = new AcademicYearModel();

        // ملاحظة: يفترض وجود عمود start_date في الجدول
        $academicYears = $model->select('id, label')
            ->orderBy('start_date', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'success'        => true,
            'academic_years' => $academicYears,
        ]);
    }

    /**
     * GET /admins/api/courses/universities/{academicYearId}
     * الجامعات المرتبطة بعام دراسي معيّن عبر جدول الربط
     */
    public function getUniversities(int $academicYearId)
    {
        $model   = new UniversityAcademicYearModel();
        $builder = $model->builder();

        $universities = $builder->select('universities.id, universities.name')
            ->join('universities', 'universities.id = university_academic_years.university_id')
            ->where('university_academic_years.academic_year_id', $academicYearId)
            ->groupBy('universities.id, universities.name') // ✔️ توافق مع ONLY_FULL_GROUP_BY
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success'      => true,
            'universities' => $universities,
        ]);
    }

    /**
     * GET /admins/api/courses/semesters/{academicYearId}
     * فصول العام المحدّد
     */
    public function getSemesters(int $academicYearId)
    {
        $model = new SemesterModel();

        $semesters = $model->select('id, name')
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success'   => true,
            'semesters' => $semesters,
        ]);
    }

    /**
     * POST /admins/api/courses/store
     * إنشاء «مساق واحد» ثم ربطه بعدّة جامعات لنفس الفصل دفعة واحدة
     * المتوقَّع: JSON يحوي university_ids[] أو university_id مفرد كـ fallback
     */
    public function store()
    {
        // ✅ حماية أساسية لطلبات الـ AJAX
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'طلب غير مصرح به.',
            ]);
        }

        $data = json_decode((string) $this->request->getBody(), true) ?? [];

        // === استخراج الجامعات: university_ids[] أو fallback إلى university_id المفرد ===
        $universityIds = $data['university_ids'] ?? null;
        if (empty($universityIds) && !empty($data['university_id'])) {
            $universityIds = [$data['university_id']];
        }

        // === تحقق الحقول ===
        foreach (['name', 'code', 'academic_year_id', 'semester_id'] as $key) {
            if (empty($data[$key])) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => "بيانات غير مكتملة. الحقل المطلوب: {$key}",
                ]);
            }
        }

        if (empty($universityIds) || !is_array($universityIds)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'يرجى اختيار جامعة واحدة على الأقل.',
            ]);
        }

        $semesterModel = new SemesterModel();
        $uayModel      = new UniversityAcademicYearModel();

        // ✅ تحقق أن الفصل يتبع نفس العام الدراسي القادم من الواجهة
        $semester = $semesterModel->find((int) $data['semester_id']);
        if (!$semester || (int) $semester['academic_year_id'] !== (int) $data['academic_year_id']) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'الفصل الدراسي المحدد لا يتبع العام الدراسي المرسل.',
            ]);
        }

        // ✅ تحقّق الجامعات: مسموح بها ضمن هذا العام؟
        $intIds = array_values(array_unique(array_map('intval', $universityIds)));
        $validRows = $uayModel->where('academic_year_id', (int) $data['academic_year_id'])
            ->whereIn('university_id', $intIds)
            ->select('university_id')
            ->findAll();

        $validSet = [];
        foreach ($validRows as $r) {
            $validSet[(int) $r['university_id']] = true;
        }

        $invalidUniversities = [];
        $filteredUids = [];
        foreach ($intIds as $uid) {
            if (isset($validSet[$uid])) {
                $filteredUids[] = $uid;
            } else {
                $invalidUniversities[] = $uid;
            }
        }

        if (empty($filteredUids)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'لم يتم العثور على جامعات مرتبطة بهذا العام الدراسي.',
                'invalid_universities' => $invalidUniversities,
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $courseModel   = new CourseModel();
        $offeringModel = new CourseOfferingModel();
        $yearModel     = new AcademicYearModel();
        $uniModel      = new UniversityModel();

        // === إنشاء المساق ===
        $courseModel->insert([
            'name'        => trim((string) $data['name']),
            'code'        => trim((string) $data['code']),
            'description' => $data['description'] ?? null,
        ]);
        $courseId = (int) $courseModel->getInsertID();

        // معلومات للملخص (عام/فصل)
        $year      = $yearModel->find((int) $data['academic_year_id']);
        $semName   = $semester['name'] ?? '';
        $yearLabel = $year['label'] ?? '';

        // === إنشاء العروض لكل جامعة مختارة (منع التكرار إن وجد عرض سابق) ===
        $added = [];
        foreach ($filteredUids as $uid) {
            $exists = $offeringModel
                ->where('course_id', $courseId)
                ->where('semester_id', (int) $data['semester_id'])
                ->where('university_id', $uid)
                ->first();

            $alreadyExists = (bool) $exists;

            if (!$alreadyExists) {
                $offeringModel->insert([
                    'course_id'     => $courseId,
                    'semester_id'   => (int) $data['semester_id'],
                    'university_id' => $uid,
                ]);
            }

            $uni = $uniModel->find($uid);

            $added[] = [
                'university_id'        => $uid,
                'university_name'      => $uni['name'] ?? "جامعة #{$uid}",
                'semester_id'          => (int) $data['semester_id'],
                'semester_name'        => $semName,
                'academic_year_id'     => (int) $data['academic_year_id'],
                'academic_year_label'  => $yearLabel,
                'already_exists'       => $alreadyExists,
            ];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'فشل في إضافة المساق. يرجى المحاولة لاحقًا.',
            ]);
        }

        return $this->response->setJSON([
            'success'               => true,
            'message'               => 'تم إضافة المساق بنجاح.',
            'course_id'             => $courseId,
            'added_offerings'       => $added,
            'invalid_universities'  => $invalidUniversities, // ✔️ للمراجعة إن وُجدت
        ]);
    }

    /**
     * GET /admins/api/courses/filter?academic_year_id=&university_id=&semester_id=
     * ترشيح المساقات بحسب (عام/جامعة/فصل)
     */
    public function getCoursesByFilters()
    {
        $academicYearId = (int) $this->request->getGet('academic_year_id');
        $universityId   = (int) $this->request->getGet('university_id');
        $semesterId     = (int) $this->request->getGet('semester_id');

        if (!$academicYearId || !$universityId || !$semesterId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'البيانات المطلوبة غير مكتملة.',
            ]);
        }

        $db = \Config\Database::connect();

        // متوافق مع ONLY_FULL_GROUP_BY
        $results = $db->query("
            SELECT 
                courses.id,
                courses.name,
                courses.code,
                universities.name AS university_name,
                COUNT(DISTINCT weekly_contents.id) AS weeks_count
            FROM course_offerings
            JOIN courses      ON courses.id      = course_offerings.course_id
            JOIN universities ON universities.id = course_offerings.university_id
            JOIN semesters    ON semesters.id    = course_offerings.semester_id
            LEFT JOIN weekly_contents 
                               ON weekly_contents.course_offering_id = course_offerings.id
            WHERE semesters.academic_year_id = ?
              AND course_offerings.university_id = ?
              AND course_offerings.semester_id   = ?
            GROUP BY courses.id, courses.name, courses.code, universities.name
            ORDER BY courses.id DESC
        ", [$academicYearId, $universityId, $semesterId])->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'courses' => $results,
        ]);
    }

    /**
     * GET /admins/api/courses/show/{id}
     * يعيد تفاصيل المساق + أحد عروضه (الأول) لملء المودال القديم
     * (يمكن لاحقًا توسيعها لتعيد مصفوفة بالعروض كلها)
     */
    public function show(int $id)
    {
        $courseModel     = new CourseModel();
        $offeringModel   = new CourseOfferingModel();
        $semesterModel   = new SemesterModel();
        $universityModel = new UniversityModel();
        $yearModel       = new AcademicYearModel();

        $course = $courseModel->find($id);
        if (!$course) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'المساق غير موجود.',
            ]);
        }

        // نلتقط أول عرض (للخلفية الحالية في الواجهة)
        $offering = $offeringModel->where('course_id', $id)->orderBy('id', 'ASC')->first();

        $semester   = $offering ? $semesterModel->find((int) $offering['semester_id']) : null;
        $university = $offering ? $universityModel->find((int) $offering['university_id']) : null;
        $year       = $semester ? $yearModel->find((int) $semester['academic_year_id']) : null;

        return $this->response->setJSON([
            'success' => true,
            'course'  => [
                'id'                  => (int) $course['id'],
                'name'                => $course['name'],
                'code'                => $course['code'],
                'description'         => $course['description'] ?? null,
                'academic_year_label' => $year['label'] ?? '',
                'university_name'     => $university['name'] ?? '',
                'semester_name'       => $semester['name'] ?? '',
            ],
        ]);
    }

    /**
     * PUT /admins/api/courses/update/{id}
     * دعم تعديل العروض لعدّة جامعات أيضًا:
     * university_ids[] أو university_id مفرد + تحديث حقول المساق الأساسية.
     */
    public function update(int $id)
    {
        // ✔️ توحيد سلوك الأمان
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'طلب غير مصرح به.',
            ]);
        }

        $data = json_decode((string) $this->request->getBody(), true) ?? [];

        // تحقق أساسي
        foreach (['name', 'code', 'semester_id', 'academic_year_id'] as $key) {
            if (empty($data[$key])) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "بيانات غير مكتملة. الحقل المطلوب: {$key}",
                ]);
            }
        }

        // الفصل يتبع العام؟
        $semesterModel = new SemesterModel();
        $semester      = $semesterModel->find((int) $data['semester_id']);
        if (!$semester || (int) $semester['academic_year_id'] !== (int) $data['academic_year_id']) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'الفصل الدراسي المحدد لا يتبع العام الدراسي المرسل.',
            ]);
        }

        // استخراج الجامعات
        $universityIds = $data['university_ids'] ?? null;
        if (empty($universityIds) && !empty($data['university_id'])) {
            $universityIds = [$data['university_id']];
        }
        if (empty($universityIds) || !is_array($universityIds)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى اختيار جامعة واحدة على الأقل.',
            ]);
        }

        // تأكيد صلاحية الجامعات ضمن العام
        $uayModel  = new UniversityAcademicYearModel();
        $intIds    = array_values(array_unique(array_map('intval', $universityIds)));
        $validRows = $uayModel->where('academic_year_id', (int) $data['academic_year_id'])
            ->whereIn('university_id', $intIds)
            ->select('university_id')
            ->findAll();

        $validSet = [];
        foreach ($validRows as $r) {
            $validSet[(int) $r['university_id']] = true;
        }

        $filteredUids = array_values(array_filter($intIds, static fn ($x) => isset($validSet[$x])));
        if (empty($filteredUids)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'لم يتم العثور على جامعات مرتبطة بهذا العام الدراسي.',
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $courseModel   = new CourseModel();
        $offeringModel = new CourseOfferingModel();
        $uniModel      = new UniversityModel();
        $yearModel     = new AcademicYearModel();

        // تحديث حقول المساق الأساسية
        $courseModel->update($id, [
            'name'        => trim((string) $data['name']),
            'code'        => trim((string) $data['code']),
            'description' => $data['description'] ?? null,
        ]);

        // حذف العروض السابقة ثم إعادة إنشائها (لنفس الفصل المحدّد)
        // ⚠️ ملاحظة: إذا كانت هناك FKs من weekly_contents لـ course_offerings بلا CASCADE،
        // سيُرجع transStatus=false ويُبلَّغ بذلك.
        $offeringModel->where('course_id', $id)->delete();

        $year      = $yearModel->find((int) $data['academic_year_id']);
        $semName   = $semester['name'] ?? '';
        $yearLabel = $year['label'] ?? '';

        $updated = [];
        foreach ($filteredUids as $uid) {
            $offeringModel->insert([
                'course_id'     => (int) $id,
                'semester_id'   => (int) $data['semester_id'],
                'university_id' => $uid,
            ]);

            $uni = $uniModel->find($uid);
            $updated[] = [
                'university_id'        => $uid,
                'university_name'      => $uni['name'] ?? "جامعة #{$uid}",
                'semester_id'          => (int) $data['semester_id'],
                'semester_name'        => $semName,
                'academic_year_id'     => (int) $data['academic_year_id'],
                'academic_year_label'  => $yearLabel,
            ];
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث.',
            ]);
        }

        return $this->response->setJSON([
            'success'           => true,
            'message'           => 'تم تحديث المساق بنجاح.',
            'updated_offerings' => $updated,
        ]);
    }

    /**
     * DELETE /admins/api/courses/delete/{id}
     * حذف المساق وجميع عروضه
     */
    public function delete(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'طلب غير مصرح به.',
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $offeringModel = new CourseOfferingModel();
        $offeringModel->where('course_id', $id)->delete();

        $courseModel = new CourseModel();
        $courseModel->delete($id);

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'حدث خطأ أثناء الحذف.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم حذف المساق بنجاح.',
        ]);
    }
}
