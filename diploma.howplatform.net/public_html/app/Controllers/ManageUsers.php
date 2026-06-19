<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\UniversityModel;
use App\Models\AcademicYearModel;
use App\Models\SpecialtyModel;
use CodeIgniter\API\ResponseTrait;

class ManageUsers extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        return view('admins/pages/manage_users');
    }
    public function list()
    {
        $m = new UserModel();
    
        // =========================
        // 1) قراءة معاملات الفلترة
        // =========================
        $roleId   = $this->request->getGet('role_id');
        $username = trim((string) $this->request->getGet('username'));
        $phone    = trim((string) $this->request->getGet('phone'));
        $email    = trim((string) $this->request->getGet('email'));
    
        // عدد السجلات في الصفحة (افتراضي 10)
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 10; // 🚦 سقف منطقي
        }
    
        // رقم الصفحة (افتراضي 1)
        $page = (int) ($this->request->getGet('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }
    
        // =========================
        // 2) تطبيق الفلاتر (على Users)
        // =========================
        if ($roleId !== null && $roleId !== '') {
            $m->where('users.account_type_id', (int) $roleId);
        }
        if ($username !== '') {
            $m->like('users.name', $username);
        }
        if ($phone !== '') {
            $m->like('users.phone_number', $phone);
        }
        if ($email !== '') {
            $m->like('users.email', $email);
        }
    
        // =========================
        // 3) إجمالي السجلات بعد الفلترة
        //    ⚠️ نستخدم نسخة ثانية من الموديل لتجنّب تأثير الـ JOIN على العدّ
        // =========================
        $countModel = new UserModel();
        if ($roleId !== null && $roleId !== '') {
            $countModel->where('users.account_type_id', (int) $roleId);
        }
        if ($username !== '') {
            $countModel->like('users.name', $username);
        }
        if ($phone !== '') {
            $countModel->like('users.phone_number', $phone);
        }
        if ($email !== '') {
            $countModel->like('users.email', $email);
        }
    
        $total = (int) $countModel->countAllResults(); // 🔢 إجمالي السجلات المطابقة
    
        // حساب الصفحات والإزاحة
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
    
        // =========================
        // 4) جلب بيانات الصفحة الحالية (مع الانضمامات)
        // =========================
        $users = $m->select(
                // ✅ نختار الحقول المطلوبة فقط
                'users.id,
                 users.name,
                 users.email,
                 users.phone_number,
                 users.profile_image_url,
                 users.gender,
                 users.account_type_id   AS role_id,
                 users.university_id     AS university_id,
                 users.academic_year_id  AS academic_year_id,
                 users.specialty_id      AS specialty_id,
                 account_types.name      AS role_name,
                 universities.name       AS university_name,
                 academic_years.label    AS academic_year,
                 specialties.name        AS specialty_name'
            )
            ->join('account_types',  'account_types.id   = users.account_type_id',   'left')
            ->join('universities',   'universities.id    = users.university_id',     'left')
            ->join('academic_years', 'academic_years.id  = users.academic_year_id',  'left')
            ->join('specialties',    'specialties.id     = users.specialty_id',      'left')
            ->orderBy('users.id', 'DESC')      // 📌 ترتيب ثابت
            ->findAll($perPage, $offset);      // 👈 باجينيشن فعلي
    
        // =========================
        // 5) إخراج JSON مع معلومات الباجينيشن
        // =========================
        return $this->respond([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'page'         => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'total_pages'  => $totalPages,
                    'has_prev'     => $page > 1,
                    'has_next'     => $page < $totalPages,
                    'prev_page'    => $page > 1 ? $page - 1 : null,
                    'next_page'    => $page < $totalPages ? $page + 1 : null,
                    'first_page'   => 1,
                    'last_page'    => $totalPages,
                ],
            ],
        ]);
    }

    public function options()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }
    
        $roleModel = new RoleModel();   // ✅
        $uniModel  = new UniversityModel();
        $yearModel = new AcademicYearModel();
        $specModel = new SpecialtyModel();     // ✅
    
        return $this->response->setJSON([
            'roles'        => $roleModel->select('id, name')             ->findAll(),
            'universities' => $uniModel ->select('id, name')             ->findAll(),
            'years'        => $yearModel->select('id, label AS name')    ->findAll(),
            'specialties'  => $specModel->select('id, name')             ->findAll(),
        ]);
    }
    public function delete()
    {
    if (! $this->request->isAJAX()) {
        return $this->failForbidden('Access denied');
    }

    $data = $this->request->getJSON(true);
    $userId = intval($data['user_id'] ?? 0);

    if (! $userId) {
        return $this->failValidationErrors('معرِّف المستخدم غير صالح');
    }

    $userModel = new UserModel();
    $user = $userModel->find($userId);

    if (! $user) {
        return $this->failNotFound('المستخدم غير موجود');
    }

    try {
        $userModel->delete($userId);
        return $this->respond(['success' => true, 'message' => 'تم حذف المستخدم']);
    } catch (\Throwable $e) {
        log_message('error', $e->getMessage());
        return $this->failServerError('فشل حذف المستخدم');
    }
}
    public function stats()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }
    
        $db = \Config\Database::connect();
    
        // ✅ تجميع بحسب الدور (اسم الدور + العدد)
        $rows = $db->table('users')
            ->select('users.account_type_id, COALESCE(account_types.name, "Unknown") AS role_name, COUNT(*) AS cnt', false)
            ->join('account_types', 'account_types.id = users.account_type_id', 'left')
            ->groupBy('users.account_type_id, account_types.name')
            ->get()
            ->getResultArray();
    
        $byRole = [];
        $students = 0;
        $teachers = 0;
        $admins   = 0;
    
        // ✅ نُطبّع أسماء الأدوار لدعم العربية/الإنجليزية
        foreach ($rows as $r) {
            $roleId   = (int) ($r['account_type_id'] ?? 0);
            $roleName = trim((string) ($r['role_name'] ?? ''));
            $count    = (int) ($r['cnt'] ?? 0);
    
            $byRole[] = [
                'role_id'   => $roleId,
                'role_name' => $roleName,
                'count'     => $count,
            ];
    
            $n = mb_strtolower($roleName);
            if (in_array($n, ['student', 'طالب', 'الطالب', 'طلاب'], true)) {
                $students += $count;
            } elseif (in_array($n, ['teacher', 'معلم', 'معلمون', 'المعلم', 'instructor'], true)) {
                $teachers += $count;
            } elseif (in_array($n, ['admin', 'أدمن', 'مشرف', 'administrator'], true)) {
                $admins += $count;
            }
        }
    
        return $this->respond([
            'success' => true,
            'data' => [
                'students' => $students,
                'teachers' => $teachers,
                'admins'   => $admins,
                'by_role'  => $byRole, // مفيدة لو حبيت تعرض توزيعًا أوسع
            ],
        ]);
    }
    public function updateAccount()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }
    
        $data = $this->request->getJSON(true);
    
        $userId = (int) ($data['user_id'] ?? 0);
        if (! $userId) {
            return $this->failValidationErrors('معرِّف المستخدم غير صالح');
        }
    
        // ✅ اقرأ القيم خام (قد تكون null/empty string)
        $roleRaw = $data['role_id'] ?? null;
        $uniRaw  = $data['university_id'] ?? null;
        $yrRaw   = $data['academic_year_id'] ?? null;
        $spRaw   = $data['specialty_id'] ?? null;
    
        // ✅ محوّل آمن: "" أو null => NULL، وإلا int
        $toNullInt = static fn($v) => ($v === '' || $v === null) ? null : (int) $v;
    
        $updateData = [
            // إن أردت الدور إلزاميًا، بدّل السطر التالي إلى (int) فقط أو افحصه قبل التحديث
            'account_type_id'  => $toNullInt($roleRaw),
            'university_id'    => $toNullInt($uniRaw),
            'academic_year_id' => $toNullInt($yrRaw),
            'specialty_id'     => $toNullInt($spRaw),
        ];
    
        $userModel = new UserModel();
        $user = $userModel->find($userId);
        if (! $user) {
            return $this->failNotFound('المستخدم غير موجود');
        }
    
        try {
            // ⚠️ لا تستخدم array_filter هنا حتى يُكتب NULL فعلاً في الأعمدة
            $userModel->update($userId, $updateData);
    
            return $this->respond([
                'success' => true,
                'message' => 'تم حفظ التعديلات بنجاح'
            ]);
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('فشل حفظ البيانات');
        }
    }

}
