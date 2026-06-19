<?php

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\SemesterModel;
use App\Models\UniversityModel;
use App\Models\UniversityAcademicYearModel;
use CodeIgniter\API\ResponseTrait;
use DateTimeImmutable;

class AcademicYearApi extends BaseController
{
    use ResponseTrait;

    protected AcademicYearModel $yearModel;
    protected SemesterModel $semesterModel;
    protected UniversityModel $universityModel;
    protected UniversityAcademicYearModel $uaModel;

    public function __construct()
    {
        $this->yearModel       = new AcademicYearModel();
        $this->semesterModel   = new SemesterModel();
        $this->universityModel = new UniversityModel();
        $this->uaModel         = new UniversityAcademicYearModel();
    }

    /** ملخص عدّادات */
    public function summary()
    {
        return $this->respond([
            'years'        => (int) $this->yearModel->countAllResults(),
            'semesters'    => (int) $this->semesterModel->countAllResults(),
            'universities' => (int) $this->universityModel->countAllResults(),
        ]);
    }

    /** إنشاء عام دراسي */
    public function storeYear()
    {
        $data = $this->request->getJSON(true) ?? [];
        // ✅ تحقّق بسيط
        if (empty($data['label']) || empty($data['start_date']) || empty($data['end_date'])) {
            return $this->failValidationErrors('الحقول مطلوبة: label, start_date, end_date');
        }

        try {
            $id = $this->yearModel->insert([
                'label'      => trim($data['label']),
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
            ], true);

            return $this->respondCreated([
                'id'    => $id,
                'label' => $data['label'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'storeYear: ' . $e->getMessage());
            return $this->failServerError('تعذّر إنشاء العام');
        }
    }

    /** تحديث عام */
    public function updateYear($id)
    {
        $id   = (int) $id;
        $data = $this->request->getJSON(true) ?? [];

        if (! $id || empty($data['label']) || empty($data['start_date']) || empty($data['end_date'])) {
            return $this->failValidationErrors('بيانات غير مكتملة');
        }

        if (! $this->yearModel->find($id)) {
            return $this->failNotFound('العام غير موجود');
        }

        try {
            $this->yearModel->update($id, [
                'label'      => trim($data['label']),
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
            ]);
            return $this->respond(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'updateYear: ' . $e->getMessage());
            return $this->failServerError('تعذّر التحديث');
        }
    }

    /** حذف عام + توابعه */
    public function deleteYear($id)
    {
        $id = (int) $id;
        if (! $id) return $this->failValidationErrors('معرّف غير صالح');

        if (! $this->yearModel->find($id)) {
            return $this->failNotFound('العام غير موجود');
        }

        try {
            // حذف الفصول التابعة
            $this->semesterModel->where('academic_year_id', $id)->delete();

            // حذف الربط مع الجامعات
            $this->uaModel->where('academic_year_id', $id)->delete();

            // حذف العام
            $this->yearModel->delete($id);

            return $this->respondDeleted(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'deleteYear: ' . $e->getMessage());
            return $this->failServerError('تعذّر الحذف');
        }
    }

    /** إنشاء فصل */
    public function storeSemester()
    {
        $data = $this->request->getJSON(true) ?? [];
        if (empty($data['academic_year_id']) || empty($data['name'])) {
            return $this->failValidationErrors('academic_year_id و name مطلوبان');
        }

        $yearId = (int) $data['academic_year_id'];
        if (! $this->yearModel->find($yearId)) {
            return $this->failNotFound('العام غير موجود');
        }

        try {
            $id = $this->semesterModel->insert([
                'academic_year_id' => $yearId,
                'name'             => trim($data['name']),
            ], true);

            return $this->respondCreated(['id' => $id]);
        } catch (\Throwable $e) {
            log_message('error', 'storeSemester: ' . $e->getMessage());
            return $this->failServerError('تعذّر إنشاء الفصل');
        }
    }

    /** حذف فصل */
    public function deleteSemester($id)
    {
        $id = (int) $id;
        if (! $id) return $this->failValidationErrors('معرّف غير صالح');

        if (! $this->semesterModel->find($id)) {
            return $this->failNotFound('الفصل غير موجود');
        }

        try {
            $this->semesterModel->delete($id);
            return $this->respondDeleted(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'deleteSemester: ' . $e->getMessage());
            return $this->failServerError('تعذّر حذف الفصل');
        }
    }

    /** تفاصيل عام (الفصول + الجامعات) */
    public function show($yearId)
    {
        $yearId = (int) $yearId;
        $year = $this->yearModel->find($yearId);
        if (! $year) {
            return $this->failNotFound('العام غير موجود');
        }

        $semesters = $this->semesterModel
            ->where('academic_year_id', $yearId)
            ->orderBy('id', 'ASC')
            ->findAll();

        $universities = $this->universityModel
            ->select('universities.id, universities.name')
            ->join('university_academic_years uay', 'uay.university_id = universities.id', 'inner')
            ->where('uay.academic_year_id', $yearId)
            ->orderBy('universities.name', 'ASC')
            ->findAll();

        return $this->respond([
            'year'         => $year,
            'semesters'    => $semesters,
            'universities' => $universities,
        ]);
    }

    /** ربط جامعات متعددة بالعام */
    public function linkUniversities($yearId)
    {
        $yearId = (int) $yearId;
        $payload = $this->request->getJSON(true) ?? [];
        $ids = array_values(array_unique(array_map('intval', (array) ($payload['university_ids'] ?? []))));

        if (! $yearId || empty($ids)) {
            return $this->failValidationErrors('yearId و university_ids مطلوبان');
        }
        if (! $this->yearModel->find($yearId)) {
            return $this->failNotFound('العام غير موجود');
        }

        // جلب الموجودة لتفادي ازدواجية المفتاح
        $existing = $this->uaModel
            ->select('university_id')
            ->where('academic_year_id', $yearId)
            ->whereIn('university_id', $ids)
            ->findAll();

        $existIds = array_map(fn($r) => (int) $r['university_id'], $existing);
        $toInsert = array_values(array_diff($ids, $existIds));

        try {
            foreach ($toInsert as $uid) {
                $this->uaModel->insert([
                    'academic_year_id' => $yearId,
                    'university_id'    => $uid,
                ]);
            }

            return $this->respond([
                'inserted'   => $toInsert,
                'duplicates' => $existIds,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'linkUniversities: ' . $e->getMessage());
            return $this->failServerError('تعذّر الربط');
        }
    }

    /** إلغاء ربط جامعة واحدة */
    public function unlinkUniversity($yearId, $uniId)
    {
        $yearId = (int) $yearId;
        $uniId  = (int) $uniId;

        if (! $yearId || ! $uniId) {
            return $this->failValidationErrors('معرّفات غير صالحة');
        }

        try {
            $this->uaModel
                ->where('academic_year_id', $yearId)
                ->where('university_id', $uniId)
                ->delete();

            return $this->respondDeleted(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'unlinkUniversity: ' . $e->getMessage());
            return $this->failServerError('تعذّر الإلغاء');
        }
    }
}
