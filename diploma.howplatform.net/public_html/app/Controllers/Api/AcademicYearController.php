<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\AcademicYearModel;
use App\Models\SemesterModel;
use App\Models\UniversityModel;
use App\Models\UniversityAcademicYearModel;

/**
 * API للأعوام الدراسية والفصول وربط الجامعات
 * جميع المسارات مسجَّلة في Routes.php تحت group('api/academic-years')
 */
class AcademicYearController extends BaseController
{
    use ResponseTrait;

    private AcademicYearModel           $yearModel;
    private SemesterModel               $semesterModel;
    private UniversityModel             $universityModel;
    private UniversityAcademicYearModel $uaModel;

    public function __construct()
    {
        $this->yearModel       = new AcademicYearModel();
        $this->semesterModel   = new SemesterModel();
        $this->universityModel = new UniversityModel();
        $this->uaModel         = new UniversityAcademicYearModel();
    }

    /* ============================================================
     *  1) لوحة الإحصاء
     * ========================================================== */
    public function summary(): ResponseInterface
    {
        return $this->respond([
            'years'        => $this->yearModel->countAllResults(),
            'semesters'    => $this->semesterModel->countAllResults(),
            'universities' => $this->universityModel->countAllResults(),
        ]);
    }

    /* ============================================================
     *  2) إنشاء عام دراسي
     * ========================================================== */
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'label'      => 'required|regex_match[/^\d{4}-\d{4}$/]|is_unique[academic_years.label]',
            'start_date' => 'required|valid_date',
            'end_date'   => 'required|valid_date',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $newId   = $this->yearModel->insert($data, true);
        $newYear = $this->yearModel->find($newId);

        $newYear['semesters']   = [];
        $newYear['universities'] = [];

        return $this->respondCreated($newYear);
    }

    /* ============================================================
     *  3) إظهار عام مع فصوله وجامعاته
     * ========================================================== */
    public function show(int $id): ResponseInterface
    {
        $year = $this->yearModel->find($id);
        if (! $year) {
            return $this->failNotFound('Year not found');
        }

        $year['semesters'] = $this->semesterModel
            ->where('academic_year_id', $id)
            ->findAll();

        $year['universities'] = $this->universityModel
            ->select('universities.id, universities.name')
            ->join(
                'university_academic_years',
                'universities.id = university_academic_years.university_id'
            )
            ->where('academic_year_id', $id)
            ->findAll();

        return $this->respond($year);
    }

    /* ============================================================
     *  4) تحديث عام دراسي
     * ========================================================== */
    public function update(int $id): ResponseInterface
    {
        if (! $this->yearModel->find($id)) {
            return $this->failNotFound('Academic year not found');
        }

        $data = $this->request->getJSON(true);
        $rules = [
            'label'      => "required|regex_match[/^\d{4}-\d{4}$/]|is_unique[academic_years.label,id,{$id}]",
            'start_date' => 'required|valid_date',
            'end_date'   => 'required|valid_date',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $this->yearModel->update($id, $data);
        $year = $this->yearModel->find($id);

        return $this->respond($year);
    }

    /* ============================================================
     *  5) حذف عام دراسي (مع فصوله وربط الجامعات)
     * ========================================================== */
    public function delete(int $id): ResponseInterface
    {
        if (! $this->yearModel->find($id)) {
            return $this->failNotFound('Academic year not found');
        }

        $this->semesterModel->where('academic_year_id', $id)->delete();
        $this->uaModel      ->where('academic_year_id', $id)->delete();
        $this->yearModel    ->delete($id);

        return $this->respond(['deleted' => true]);
    }

    /* ============================================================
     *  6) إنشاء فصل
     * ========================================================== */
    public function createSemester(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'academic_year_id' => 'required|is_not_unique[academic_years.id]',
            'name'             => 'required|min_length[3]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->semesterModel->insert($data, true);
        return $this->respondCreated(['id' => $id]);
    }

    /* ============================================================
     *  7) حذف فصل
     * ========================================================== */
    public function deleteSemester(int $id): ResponseInterface
    {
        $semester = $this->semesterModel->find($id);
        if (! $semester) {
            return $this->failNotFound('Semester not found');
        }

        $this->semesterModel->delete($id);
        return $this->respond(['deleted' => true, 'semester_id' => $id]);
    }

    /* ============================================================
     *  8) ربط جامعات مع عام (بدون حذف القديم)
     * ========================================================== */
    public function linkUniversities(int $yearId): ResponseInterface
    {
        $payload = $this->request->getJSON(true);
        $ids     = $payload['university_ids'] ?? [];

        if (! is_array($ids) || ! $ids) {
            return $this->failValidationErrors(['university_ids' => 'قائمة الجامعات مطلوبة']);
        }

        if (! $this->yearModel->find($yearId)) {
            return $this->failNotFound('Academic year not found');
        }

        $ids = $this->universityModel
            ->whereIn('id', $ids)
            ->findColumn('id');

        if (! $ids) {
            return $this->failValidationErrors(['university_ids' => 'لا توجد جامعات صالحة']);
        }

        $existing = $this->uaModel
            ->select('university_id')
            ->where('academic_year_id', $yearId)
            ->whereIn('university_id', $ids)
            ->findColumn('university_id');

        $newIds     = array_diff($ids, $existing);
        $duplicates = array_values($existing);

        if (! $newIds) {
            return $this->respond(
                ['linked' => 0, 'duplicates' => $duplicates],
                409
            );
        }

        $batch = array_map(
            static fn ($u) => ['academic_year_id' => $yearId, 'university_id' => $u],
            $newIds
        );
        $this->uaModel->insertBatch($batch);

        return $this->respond(
            ['linked' => count($newIds), 'duplicates' => $duplicates]
        );
    }

    /* ============================================================
     *  9) إلغاء ربط جامعة واحدة
     * ========================================================== */
    public function unlinkUniversity(int $yearId, int $uniId): ResponseInterface
    {
        $deleted = $this->uaModel
            ->where('academic_year_id', $yearId)
            ->where('university_id', $uniId)
            ->delete();

        if (! $deleted) {
            return $this->failNotFound('Link not found');
        }

        return $this->respond(['deleted' => true]);
    }
}
