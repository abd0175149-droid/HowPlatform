<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\{
    UniversityModel,
    SpecialtyModel,
    UniversitySpecialtyModel,
    DiplomaTypeModel           // ✨ موديل نوع الدبلوم
};

/**
 * لوحة تحكم «الجامعات / التخصّصات / أنواع الدبلوم»
 */
class UniversityOptions extends Controller
{
    use ResponseTrait;

    private UniversityModel          $uniModel;
    private SpecialtyModel           $specModel;
    private UniversitySpecialtyModel $usModel;
    private DiplomaTypeModel         $dipModel;
    private $db;                                   // اتصال قاعدة البيانات

    public function __construct()
    {
        $this->uniModel  = new UniversityModel();
        $this->specModel = new SpecialtyModel();
        $this->usModel   = new UniversitySpecialtyModel();
        $this->dipModel  = new DiplomaTypeModel();
        $this->db        = Database::connect();
    }

    /*──────────────────────────────
     | 1)  صفحة HTML للأدمن
     *─────────────────────────────*/
    public function index()
    {
        /* البطاقات */
        $data['universitiesCount'] = $this->uniModel->countAllResults();
        $data['specialtiesCount']  = $this->specModel->countAllResults();

        /* خيارات الـ <select> و الـ checkboxes */
        $data['uniOptions']      = $this->uniModel ->orderBy('name','ASC')->findAll();
        $data['specOptions']     = $this->specModel->orderBy('name','ASC')->findAll();
        $data['diplomaOptions']  = $this->dipModel ->orderBy('name','ASC')->findAll();   // ✨

        /* الكروت: الجامعة + نوع الدبلوم + التخصّصات */
        $data['universities'] = $this->uniModel
            ->select('universities.*, diploma_types.name AS diploma')
            ->join('university_diploma_types','university_diploma_types.university_id = universities.id','left')
            ->join('diploma_types','diploma_types.id = university_diploma_types.diploma_type_id','left')
            ->orderBy('universities.name','ASC')
            ->findAll();

        foreach ($data['universities'] as &$u) {
            $u['specialties'] = $this->specModel
                ->select('specialties.id, specialties.name')
                ->join('university_specialties','specialties.id = university_specialties.specialty_id')
                ->where('university_id',$u['id'])
                ->findAll();
        }
        unset($u);

        return view('admins/pages/university_options', $data);
    }

    /*──────────────────────────────
     | 2)  لوحة الإحصاء
     *─────────────────────────────*/
    public function summary()
    {
        return $this->respond([
            'universities' => $this->uniModel->countAllResults(),
            'specialties'  => $this->specModel->countAllResults(),
        ]);
    }

    /*──────────────────────────────
     | 3)  إنشاء جامعة + ربط نوع الدبلوم
     *─────────────────────────────*/
    public function createUniversity()
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'name'            => 'required|is_unique[universities.name]',
            'diploma_type_id' => 'required|is_not_unique[diploma_types.id]',
        ];
        if (! $this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        /* ❶ إنشاء الجامعة */
        $uniId = $this->uniModel->insert(['name' => $data['name']], true);

        /* ❷ ربطها بنوع الدبلوم */
        $this->db->table('university_diploma_types')->insert([
            'university_id'   => $uniId,
            'diploma_type_id' => $data['diploma_type_id'],
        ]);

        /* ❸ إرجاع السجل مع اسم الدبلوم */
        $uni = $this->uniModel
            ->select('universities.*, diploma_types.name AS diploma')
            ->join('university_diploma_types','university_diploma_types.university_id = universities.id')
            ->join('diploma_types','diploma_types.id = university_diploma_types.diploma_type_id')
            ->find($uniId);

        $uni['specialties'] = [];

        return $this->respondCreated($uni);
    }

    /* إحضار جامعة واحدة مع التخصّصات */
    public function show(int $id)
    {
        $uni = $this->uniModel
            ->select('universities.*, diploma_types.name AS diploma')
            ->join('university_diploma_types','university_diploma_types.university_id = universities.id','left')
            ->join('diploma_types','diploma_types.id = university_diploma_types.diploma_type_id','left')
            ->find($id);

        if (! $uni) {
            return $this->failNotFound('University not found');
        }

        $uni['specialties'] = $this->specModel
            ->select('specialties.id, specialties.name')
            ->join('university_specialties','specialties.id = university_specialties.specialty_id')
            ->where('university_id',$id)->findAll();

        return $this->respond($uni);
    }

    /* حذف جامعة (وكل ما يتبعها) */
    public function delete(int $id)
    {
        if (! $this->uniModel->find($id)) {
            return $this->failNotFound('University not found');
        }

        // حذف الروابط مع التخصّصات
        $this->usModel->where('university_id', $id)->delete();
        // حذف الروابط مع أنواع الدبلوم
        $this->db->table('university_diploma_types')
                 ->where('university_id', $id)
                 ->delete();
        // أخيرًا حذف الجامعة
        $this->uniModel->delete($id);

        return $this->respond(['deleted' => true]);
    }

    /*──────────────────────────────
     | 4)  إنشاء تخصّص
     *─────────────────────────────*/
    public function createSpecialty()
    {
        $data = $this->request->getJSON(true);

        if (! $this->validateData($data, ['name'=>'required|is_unique[specialties.name]'])) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        $id = $this->specModel->insert($data, true);

        return $this->respondCreated(['id'=>$id,'name'=>$data['name']]);
    }

    /*──────────────────────────────
     | 5)  ربط / إلغاء ربط تخصّصات
     *─────────────────────────────*/
    public function linkSpecialties(int $uniId)
    {
        $ids = $this->request->getJSON(true)['specialty_ids'] ?? [];

        if (! $ids) {
            return $this->failValidationErrors(['specialty_ids'=>'مطلوب']);
        }
        if (! $this->uniModel->find($uniId)) {
            return $this->failNotFound('University not found');
        }

        $ids = $this->specModel->whereIn('id', $ids)->findColumn('id');
        if (! $ids) {
            return $this->failValidationErrors(['specialty_ids'=>'لا تخصصات صالحة']);
        }

        $existing = $this->usModel
            ->where('university_id', $uniId)
            ->whereIn('specialty_id', $ids)
            ->findColumn('specialty_id');

        $newIds = array_diff($ids, $existing);
        if (! $newIds) {
            return $this->respond(['linked'=>0,'duplicates'=>$existing], 409);
        }

        $batch = array_map(
            static fn($s)=>['university_id'=>$uniId,'specialty_id'=>$s],
            $newIds
        );
        $this->usModel->insertBatch($batch);

        return $this->respond(['linked'=>count($newIds),'duplicates'=>$existing]);
    }

    public function unlinkSpecialty(int $uniId, int $specId)
    {
        $deleted = $this->usModel
            ->where('university_id', $uniId)
            ->where('specialty_id', $specId)
            ->delete();

        return $deleted
            ? $this->respond(['deleted'=>true])
            : $this->failNotFound('Link not found');
    }

    /* حذف تخصّص نهائياً */
    public function deleteSpecialty(int $id)
    {
        if (! $this->specModel->find($id)) {
            return $this->failNotFound('Specialty not found');
        }

        $this->usModel->where('specialty_id', $id)->delete();   // روابط الجامعات
        $this->specModel->delete($id, true);                    // حذف التخصّص

        return $this->respond(['deleted'=>true]);
    }
}
