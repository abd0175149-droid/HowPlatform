<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\{UniversityModel, SpecialtyModel, UniversitySpecialtyModel};
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API الجامعات – إنشاء / حذف / ربط بالتخصصات
 */
class UniversityController extends BaseController
{
    use ResponseTrait;

    private UniversityModel          $uniModel;
    private SpecialtyModel           $specModel;
    private UniversitySpecialtyModel $usModel;

    public function __construct()
    {
        $this->uniModel  = new UniversityModel();
        $this->specModel = new SpecialtyModel();
        $this->usModel   = new UniversitySpecialtyModel();
    }

    /* ───── لوحة الإحصاء ───── */
    public function summary(): ResponseInterface
    {
        return $this->respond([
            'universities' => $this->uniModel ->countAllResults(),
            'specialties'  => $this->specModel->countAllResults(),
        ]);
    }

    /* ───── إنشاء جامعة ───── */
    public function createUniversity(): ResponseInterface
    {
        $data = $this->request->getJSON(true);
        $rules = ['name'=>'required|is_unique[universities.name]','city'=>'permit_empty'];
        if (! $this->validateData($data, $rules))
            return $this->failValidationErrors($this->validator->getErrors());

        $id   = $this->uniModel->insert($data,true);
        $item = $this->uniModel->find($id);
        $item['specialties'] = [];
        return $this->respondCreated($item);
    }

    /* ───── إنشاء تخصّص ───── */
    public function createSpecialty(): ResponseInterface
    {
        $data = $this->request->getJSON(true);
        if (! $this->validateData($data, ['name'=>'required|is_unique[specialties.name]']))
            return $this->failValidationErrors($this->validator->getErrors());

        $id = $this->specModel->insert($data,true);
        return $this->respondCreated(['id'=>$id,'name'=>$data['name']]);
    }

    /* ───── ربط جامعة بتخصّصات ─────
       body: { specialty_ids:[1,2] }
     */
    public function linkSpecialties(int $uniId): ResponseInterface
    {
        $ids = $this->request->getJSON(true)['specialty_ids'] ?? [];
        if (! $ids) return $this->failValidationErrors(['specialty_ids'=>'مطلوب']);

        if (! $this->uniModel->find($uniId))
            return $this->failNotFound('University not found');

        $ids = $this->specModel->whereIn('id',$ids)->findColumn('id');
        if (! $ids) return $this->failValidationErrors(['specialty_ids'=>'لا تخصصات صالحة']);

        $existing = $this->usModel
            ->select('specialty_id')
            ->where('university_id',$uniId)
            ->whereIn('specialty_id',$ids)
            ->findColumn('specialty_id');

        $newIds = array_diff($ids,$existing);
        if (! $newIds)
            return $this->respond(['linked'=>0,'duplicates'=>$existing],409);

        $batch = array_map(fn($s)=>['university_id'=>$uniId,'specialty_id'=>$s],$newIds);
        $this->usModel->insertBatch($batch);

        return $this->respond(['linked'=>count($newIds),'duplicates'=>$existing]);
    }

    /* ───── إلغاء ربط تخصّص ───── */
    public function unlinkSpecialty(int $uniId,int $specId): ResponseInterface
    {
        $deleted = $this->usModel
            ->where('university_id',$uniId)
            ->where('specialty_id',$specId)
            ->delete();
        return $deleted ? $this->respond(['deleted'=>true])
                        : $this->failNotFound('Link not found');
    }

    /* ───── جلب جامعة مع تخصّصاتها ───── */
    public function show(int $id): ResponseInterface
    {
        $uni = $this->uniModel->find($id);
        if (!$uni) return $this->failNotFound('University not found');

        $uni['specialties'] = $this->specModel
            ->select('specialties.id,specialties.name')
            ->join('university_specialties','specialties.id = university_specialties.specialty_id')
            ->where('university_id',$id)->findAll();
        return $this->respond($uni);
    }

    /* ───── حذف جامعة مع روابطها ───── */
    public function delete(int $id): ResponseInterface
    {
        if (! $this->uniModel->find($id))
            return $this->failNotFound('University not found');

        $this->usModel->where('university_id',$id)->delete();
        $this->uniModel->delete($id);
        return $this->respond(['deleted'=>true]);
    }
}
