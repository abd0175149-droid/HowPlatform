<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UniversityModel;
use App\Models\SpecialtyModel;
use App\Models\DiplomaTypeModel;
use App\Models\AcademicYearModel;
use App\Services\RewardsService;

class NewProfileController extends BaseController
{
    protected $universityModel;
    protected $specialtyModel;
    protected $diplomaTypeModel;
    protected $academicYearModel;

    public function __construct()
    {
        $this->universityModel   = new UniversityModel();
        $this->specialtyModel    = new SpecialtyModel();
        $this->diplomaTypeModel  = new DiplomaTypeModel();
        $this->academicYearModel = new AcademicYearModel();
    }

    public function completeProfile()
    {
        $authUser = session('auth_user'); // من AuthFilter
        if (!$authUser || empty($authUser['id'])) {
            return redirect()->to('https://auth.howplatform.net/login?redirect=' . current_url());
        }
    
        $userId = $authUser['id'];
    
        $db = \Config\Database::connect();
        $diplomaUser = $db->table('diploma_users')
                          ->where('user_id', $userId)
                          ->get()
                          ->getRowArray();
    
        // جلب أنواع الدبلوم
        $diplomaTypes = $db->table('diploma_types')->select('id, name')->get()->getResultArray();
    
        // الجامعات المرتبطة بأنواع الدبلوم
        $uniDiplomaMap = $db->table('university_diploma_types')
            ->select('diploma_type_id, university_id, universities.name as university_name')
            ->join('universities', 'universities.id = university_diploma_types.university_id')
            ->get()->getResultArray();
    
        // التخصصات
        $specialties = $db->table('university_specialties')
            ->select('university_id, specialties.id as specialty_id, specialties.name as specialty_name')
            ->join('specialties', 'specialties.id = university_specialties.specialty_id')
            ->get()->getResultArray();
    
        // الأعوام
        $academicYears = $db->table('university_academic_years')
            ->select('university_id, academic_years.id as year_id, academic_years.label as year_name')
            ->join('academic_years', 'academic_years.id = university_academic_years.academic_year_id')
            ->get()->getResultArray();
    
        // الجامعات الأساسية (للعرض مباشرة)
        $universities = [];
        foreach ($uniDiplomaMap as $row) {
            $universities[$row['university_id']] = $row['university_name'];
        }
        $universities = array_map(fn($id, $name) => ['id' => $id, 'name' => $name], array_keys($universities), $universities);
    
        return view('partials/header')
             . view('user/complete_profile', [
                'user'              => array_merge($authUser, $diplomaUser ?? []),
                'diploma_types'     => $diplomaTypes,
                'universities'      => $universities,
                'universities_map'  => $uniDiplomaMap,
                'specialties_map'   => $specialties,
                'academic_years_map'=> $academicYears
             ])
             . view('partials/footer');
    }
    
    public function saveProfileWOrk()
    {
        $authUser = session('auth_user'); // جاي من JWT AuthFilter
        if (!$authUser || empty($authUser['id'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً.'
            ]);
        }
    
        $userId = $authUser['id'];
    
        // البيانات القادمة من النموذج
        $data = [
            'user_id'         => $userId,
            'phone_number'    => $this->request->getPost('phone_number'),
            'diploma_type_id' => $this->request->getPost('diploma_type_id'),
            'university_id'   => $this->request->getPost('university_id'),
            'specialty_id'    => $this->request->getPost('specialty_id'),
            'academic_year_id'=> $this->request->getPost('academic_year_id'),
            'gender'          => $this->request->getPost('gender'),
            'birth_date'      => $this->request->getPost('birth_date'),
            'is_complete'     => 1,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
    
        $db = \Config\Database::connect();
        $builder = $db->table('diploma_users');
    
        // تحقق: هل للمستخدم سجل موجود مسبقاً؟
        $exists = $builder->where('user_id', $userId)->get()->getRowArray();
    
        if ($exists) {
            // ✅ تحديث البيانات
            $builder->where('user_id', $userId)->update($data);
        } else {
            // ✅ إضافة سجل جديد
            $data['created_at'] = date('Y-m-d H:i:s');
            $builder->insert($data);
        }
    
        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'تم حفظ البيانات بنجاح ✅',
            'redirect' => site_url('dashboard') // عدل حسب المكان المناسب عندك
        ]);
    }
    public function saveProfile()
    {
        $authUser = session('auth_user') ?? [];
        if (empty($authUser['id'])) {
            // غير مسجل دخول
            $loginUrl = 'https://auth.howplatform.net/login?redirect=' . urlencode(current_url());
            return $this->request->isAJAX()
                ? $this->response->setJSON(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً.', 'redirect' => $loginUrl])->setStatusCode(401)
                : redirect()->to($loginUrl);
        }
    
        $userId = (int)$authUser['id'];
    
        // 📨 حقول النموذج (إدخال موحّد؛ نظف القيم الفارغة حيث يلزم)
        $payload = [
            'phone_number'     => trim((string)$this->request->getPost('phone_number')),
            'diploma_type_id'  => (int)$this->request->getPost('diploma_type_id'),
            'university_id'    => (int)$this->request->getPost('university_id'),
            'specialty_id'     => (int)$this->request->getPost('specialty_id'),
            'academic_year_id' => (int)$this->request->getPost('academic_year_id'),
            'gender'           => (string)$this->request->getPost('gender'),
            'birth_date'       => $this->request->getPost('birth_date') ?: null, // null لو فاضي
            'is_complete'      => 1, // ✅ نضبطها نحن
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
    
        $db = \Config\Database::connect();
        $db->transStart();
    
        // 🔁 Upsert في diploma_users
        $builder = $db->table('diploma_users');
        $exists  = $builder->select('id')->where('user_id', $userId)->get()->getRowArray();
    
        if ($exists) {
            $builder->where('user_id', $userId)->update($payload);
        } else {
            $payload['user_id']    = $userId;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $builder->insert($payload);
        }
    
        $db->transComplete();
        if (!$db->transStatus()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'تعذر حفظ البيانات. حاول مرة أخرى.'
            ])->setStatusCode(500);
        }
    
        // ✅ حدّث السيشن فورًا (نستخدم مفتاحًا صريحًا للدبلوم)
        $authUser['diploma_is_complete'] = 1;
        // أو بنية متداخلة:
        $authUser['platforms']['diploma']['is_complete'] = 1;
        session()->set('auth_user', $authUser);

        // ✅ التحقق من إكمال البروفايل ومنح مكافأة الدعوة (إن وُجدت)
        try {
            $rewardsService = new RewardsService();
            $rewardsService->checkAndRewardReferral($userId);
        } catch (\Throwable $e) {
            // لا نريد أن يعطل حفظ البروفايل في حالة حدوث خطأ
            log_message('error', 'NewProfileController::saveProfile - RewardsService error: ' . $e->getMessage());
        }
    
        // 🎯 مسار العودة
        $redirect = $this->request->getGet('redirect')
            ?: $this->request->getPost('redirect')
            ?: site_url('students');
    
        if ($this->request->isAJAX() || str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->response->setJSON([
                'success'  => true,
                'message'  => 'تم حفظ البيانات بنجاح ✅',
                'redirect' => $redirect
            ]);
        }
    
        return redirect()->to($redirect);
    }


}
