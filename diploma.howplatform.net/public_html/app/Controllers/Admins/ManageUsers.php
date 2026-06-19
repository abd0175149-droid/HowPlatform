<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;
use App\Models\RoleModel;
use App\Models\UniversityModel;
use App\Models\AcademicYearModel;
use App\Models\SpecialtyModel;

class ManageUsers extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        return view('admins/pages/manage_users');
    }

    /**
     * إرجاع قائمة المستخدمين مع باجينيشن + فلاتر
     * المنطق:
     *  - الأساس من default.diploma_users
     *  - تكملة الحقول (name/email/avatar) من remote.users
     */
    public function list()
    {
        $dbDefault = Database::connect('default');
        $dbRemote  = Database::connect('remote');

        // =========================
        // 1) قراءة معاملات الفلترة
        // =========================
        $roleId   = $this->request->getGet('role_id');        // من diploma_users.account_type_id
        $username = trim((string) $this->request->getGet('username')); // من users.name
        $phone    = trim((string) $this->request->getGet('phone'));    // من diploma_users.phone_number
        $email    = trim((string) $this->request->getGet('email'));    // من users.email
        // ... أعلى list() بعد قراءة بقية الفلاتر
        $complete = $this->request->getGet('complete'); // '1' أو '0' أو ''

        // باجينيشن آمن
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 10;
        }
        $page = (int) ($this->request->getGet('page') ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        // ======================================================
        // 2) إن وُجدت فلاتر تخص remote.users (name/email) جلب IDs
        // ======================================================
        $remoteIdsFilter = null; // null يعني لا تقييد من remote
        if ($username !== '' || $email !== '') {
            $r = $dbRemote->table('users')->select('id');

            if ($username !== '') {
                // like افتراضيًا آمن ويهتم بالهروب
                $r->like('name', $username);
            }
            if ($email !== '') {
                $r->like('email', $email);
            }

            $ids = array_map(
                'intval',
                array_column($r->get()->getResultArray(), 'id')
            );

            // لو لم يُطابق شيء في remote وأن هناك فلتر هناك -> يعني لا نتائج
            if (empty($ids)) {
                return $this->respond([
                    'success' => true,
                    'data'    => [
                        'users' => [],
                        'pagination' => [
                            'page' => $page, 'per_page' => $perPage,
                            'total' => 0, 'total_pages' => 1,
                            'has_prev' => false, 'has_next' => false,
                            'prev_page' => null, 'next_page' => null,
                            'first_page' => 1, 'last_page' => 1,
                        ],
                    ],
                ]);
            }
            $remoteIdsFilter = $ids;
        }

        // ======================================================
        // 3) العدّ الإجمالي بعد الفلاتر (على diploma_users)
        // ======================================================
        $countB = $dbDefault->table('diploma_users AS du'); // الأساس
        $this->applyDiplomaFilters($countB, $roleId, $phone, $remoteIdsFilter, $complete);

        $total = (int) $countB->countAllResults();

        // حساب الصفحات والإزاحة
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // ======================================================
        // 4) جلب الصفحة الحالية + أسماء المراجع من جداول القاموس
        // ======================================================
        $b = $dbDefault->table('diploma_users AS du')
            ->select(
                // حقول من default
                'du.user_id,
                 du.phone_number,
                 du.gender,
                 du.account_type_id   AS role_id,
                 du.university_id     AS university_id,
                 du.academic_year_id  AS academic_year_id,
                 du.specialty_id      AS specialty_id,
                 du.is_premium,
                 du.premium_until,
                 at.name              AS role_name,
                 u.name               AS university_name,
                 ay.label             AS academic_year,
                 sp.name              AS specialty_name'
            )
            // Joins داخل نفس قاعدة default فقط
            ->join('account_types AS at', 'at.id = du.account_type_id', 'left')
            ->join('universities  AS u',  'u.id  = du.university_id',   'left')
            ->join('academic_years AS ay','ay.id = du.academic_year_id','left')
            ->join('specialties    AS sp','sp.id = du.specialty_id',    'left')
            ->orderBy('du.id', 'DESC');

        $this->applyDiplomaFilters($b, $roleId, $phone, $remoteIdsFilter, $complete);

        $rows = $b->get($perPage, $offset)->getResultArray();

        // IDs الظاهرة بالصفحة
        $userIds = array_map(static fn ($r) => (int) $r['user_id'], $rows);
        $remoteMap = [];
        if (! empty($userIds)) {
            // جلب الاسم/الإيميل/الصورة من remote.users
            $remoteRows = $dbRemote->table('users')
                ->select('id, name, email, avatar, is_admin, is_sales, last_login_at')
                ->whereIn('id', $userIds)
                ->get()->getResultArray();

            foreach ($remoteRows as $rr) {
                $remoteMap[(int) $rr['id']] = $rr;
            }
        }

        // دمج النتائج
        $users = [];
        foreach ($rows as $r) {
            $rid = (int) $r['user_id'];
            $remote = $remoteMap[$rid] ?? null;

            $users[] = [
                // من remote (إن وجد)
                'id'               => $rid, // المفتاح الموحد
                'name'             => $remote['name']  ?? null,
                'email'            => $remote['email'] ?? null,
                'profile_image_url'=> $remote['avatar'] ?? null,

                // من default
                'phone_number'     => $r['phone_number'],
                'gender'           => $r['gender'],
                'role_id'          => $r['role_id'],
                'role_name'        => $r['role_name'],
                'university_id'    => $r['university_id'],
                'university_name'  => $r['university_name'],
                'academic_year_id' => $r['academic_year_id'],
                'academic_year'    => $r['academic_year'],
                'specialty_id'     => $r['specialty_id'],
                'specialty_name'   => $r['specialty_name'],
                'is_premium'       => (int) $r['is_premium'],
                'premium_until'    => $r['premium_until'],
            ];
        }

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

  
/**
 * فلاتر مشتركة تُطبّق على diploma_users
 *
 * @param \CodeIgniter\Database\BaseBuilder $b
 * @param mixed $roleId
 * @param string $phone
 * @param array<int>|null $remoteIdsFilter
 * @param mixed $complete  '1' أو '0' أو ''
 */
private function applyDiplomaFilters($b, $roleId, string $phone, ?array $remoteIdsFilter, $complete): void
{
    if ($roleId !== null && $roleId !== '') {
        $b->where('du.account_type_id', (int) $roleId);
    }
    if ($phone !== '') {
        $b->like('du.phone_number', $phone);
    }
    if (is_array($remoteIdsFilter)) {
        $b->whereIn('du.user_id', $remoteIdsFilter);
    }
    // ✅ فلترة اكتمال الملف
    if ($complete !== null && $complete !== '') {
        // نقبل '1'/'0' كسلاسل أو أرقام
        $val = ((string) $complete === '1') ? 1 : 0;
        $b->where('du.is_complete', $val);
        // إن أردت اعتبار NULL = غير مكتمل، استبدل السطر السابق بـ:
        // $val === 1 ? $b->where('du.is_complete', 1) : $b->groupStart()->where('du.is_complete', 0)->orWhere('du.is_complete IS NULL', null, false)->groupEnd();
    }
}

    /**
     * خيارات حقول القوائم (من قاعدة default)
     */
    public function options()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }

        $roleModel = new RoleModel();
        $uniModel  = new UniversityModel();
        $yearModel = new AcademicYearModel();
        $specModel = new SpecialtyModel();

        return $this->response->setJSON([
            'roles'        => $roleModel->select('id, name')->findAll(),
            'universities' => $uniModel ->select('id, name')->findAll(),
            'years'        => $yearModel->select('id, label AS name')->findAll(),
            'specialties'  => $specModel->select('id, name')->findAll(),
        ]);
    }

    /**
     * حذف سجل المستخدم من جدول diploma_users فقط (لا نحذف من remote.users).
     */
    public function delete()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }

        $data   = $this->request->getJSON(true);
        $userId = (int) ($data['user_id'] ?? 0); // هذا هو users.id = diploma_users.user_id

        if (! $userId) {
            return $this->failValidationErrors('معرّف المستخدم غير صالح');
        }

        $dbDefault = Database::connect('default');

        $exists = $dbDefault->table('diploma_users')->where('user_id', $userId)->get()->getRowArray();
        if (! $exists) {
            return $this->failNotFound('المستخدم غير موجود في سجلات الدبلوم');
        }

        try {
            $dbDefault->table('diploma_users')->where('user_id', $userId)->delete();
            return $this->respond(['success' => true, 'message' => 'تم حذف سجل المستخدم من الدبلوم']);
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('فشل حذف المستخدم');
        }
    }

    /**
     * إحصاءات حسب الأدوار من جدول diploma_users
     */
    public function stats()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }

        $db = Database::connect('default');

        $rows = $db->table('diploma_users AS du')
            ->select('du.account_type_id, COALESCE(at.name, "Unknown") AS role_name, COUNT(*) AS cnt', false)
            ->join('account_types AS at', 'at.id = du.account_type_id', 'left')
            ->groupBy('du.account_type_id, at.name')
            ->get()
            ->getResultArray();

        $byRole   = [];
        $students = 0;
        $teachers = 0;
        $admins   = 0;

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
                'by_role'  => $byRole,
            ],
        ]);
    }

    /**
     * تحديث حقول الحساب (تخص جدول الدبلوم فقط)
     * نتعامل مع nulls بشكل صريح.
     */
    public function updateAccount()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden('Access denied');
        }

        $data = $this->request->getJSON(true);

        $userId = (int) ($data['user_id'] ?? 0); // users.id
        if (! $userId) {
            return $this->failValidationErrors('معرّف المستخدم غير صالح');
        }

        $roleRaw = $data['role_id'] ?? null;
        $uniRaw  = $data['university_id'] ?? null;
        $yrRaw   = $data['academic_year_id'] ?? null;
        $spRaw   = $data['specialty_id'] ?? null;

        $toNullInt = static fn($v) => ($v === '' || $v === null) ? null : (int) $v;

        $updateData = [
            'account_type_id'  => $toNullInt($roleRaw),
            'university_id'    => $toNullInt($uniRaw),
            'academic_year_id' => $toNullInt($yrRaw),
            'specialty_id'     => $toNullInt($spRaw),
        ];

        $db = Database::connect('default');

        $row = $db->table('diploma_users')->where('user_id', $userId)->get()->getRowArray();
        try {
            if ($row) {
                // تحديث
                $db->table('diploma_users')->where('user_id', $userId)->update($updateData);
            } else {
                // إنشاء سجل جديد عند الحاجة
                $updateData['user_id'] = $userId;
                $db->table('diploma_users')->insert($updateData);
            }

            return $this->respond([
                'success' => true,
                'message' => 'تم حفظ التعديلات بنجاح',
            ]);
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('فشل حفظ البيانات');
        }
    }
}
