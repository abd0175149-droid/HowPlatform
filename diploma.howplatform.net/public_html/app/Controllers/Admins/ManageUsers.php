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
use App\Libraries\NotificationService;

class ManageUsers extends BaseController
{
    use ResponseTrait;

    /** الجداول المملوكة للمستخدم في قاعدة الدبلوم — تُحذف معه (نطاق: الدبلوم + بياناته المرتبطة). */
    private const USER_OWNED_TABLES = [
        'user_devices', 'user_active_sessions', 'notifications',
        'rewards_daily_logins', 'rewards_consecutive_days', 'rewards_points_log',
        'rewards_transactions', 'rewards_users', 'ai_jobs',
        'fateen_usage_logs', 'file_downloads',
    ];

    public function index()
    {
        return view('admins/pages/manage_users');
    }

    /* ====================================================================
     *  قائمة المستخدمين — أساس من diploma_users + إثراء الاسم/البريد/الصورة من remote.users
     *  فلاتر: دور / اسم / هاتف / بريد / اكتمال / تاريخ تسجيل / نشاط / مجدول للحذف + ترتيب
     * ==================================================================== */
    public function list()
    {
        $dbDefault = Database::connect('default');
        $dbRemote  = Database::connect('remote');

        $roleId   = $this->request->getGet('role_id');
        $username = trim((string) $this->request->getGet('username'));
        $phone    = trim((string) $this->request->getGet('phone'));
        $email    = trim((string) $this->request->getGet('email'));
        $complete = $this->request->getGet('complete');

        $perPage = (int) ($this->request->getGet('per_page') ?? 10);
        if ($perPage <= 0 || $perPage > 100) $perPage = 10;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));

        // فلتر اسم/بريد يستلزم جلب IDs من remote أولاً
        $remoteIdsFilter = null;
        if ($username !== '' || $email !== '') {
            $r = $dbRemote->table('users')->select('id');
            if ($username !== '') $r->like('name', $username);
            if ($email !== '')    $r->like('email', $email);
            $ids = array_map('intval', array_column($r->get()->getResultArray(), 'id'));
            if (empty($ids)) {
                return $this->respond($this->emptyListPayload($page, $perPage));
            }
            $remoteIdsFilter = $ids;
        }

        // العدّ الإجمالي بعد الفلاتر
        $countB = $dbDefault->table('diploma_users AS du');
        $this->applyDiplomaFilters($countB, $roleId, $phone, $remoteIdsFilter, $complete);
        $total = (int) $countB->countAllResults();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        // الترتيب
        [$sortCol, $sortDir] = $this->resolveSort((string) $this->request->getGet('sort'));

        $b = $dbDefault->table('diploma_users AS du')
            ->select(
                'du.user_id, du.phone_number, du.gender,
                 du.account_type_id   AS role_id,
                 du.university_id, du.academic_year_id, du.specialty_id,
                 du.is_premium, du.premium_until, du.is_complete,
                 du.created_at, du.last_login_at, du.last_seen_at,
                 du.deletion_scheduled_at, du.deletion_notified_at,
                 at.name  AS role_name,
                 u.name   AS university_name,
                 ay.label AS academic_year,
                 sp.name  AS specialty_name'
            )
            ->join('account_types AS at', 'at.id = du.account_type_id', 'left')
            ->join('universities  AS u',  'u.id  = du.university_id',   'left')
            ->join('academic_years AS ay','ay.id = du.academic_year_id','left')
            ->join('specialties    AS sp','sp.id = du.specialty_id',    'left')
            ->orderBy($sortCol, $sortDir);

        $this->applyDiplomaFilters($b, $roleId, $phone, $remoteIdsFilter, $complete);
        $rows = $b->get($perPage, $offset)->getResultArray();

        // إثراء من remote.users
        $userIds = array_map(static fn ($r) => (int) $r['user_id'], $rows);
        $remoteMap = [];
        if (! empty($userIds)) {
            $remoteRows = $dbRemote->table('users')
                ->select('id, name, email, avatar')
                ->whereIn('id', $userIds)->get()->getResultArray();
            foreach ($remoteRows as $rr) $remoteMap[(int) $rr['id']] = $rr;
        }

        $users = [];
        foreach ($rows as $r) {
            $rid    = (int) $r['user_id'];
            $remote = $remoteMap[$rid] ?? null;
            $users[] = [
                'id'                    => $rid,
                'name'                  => $remote['name']  ?? null,
                'email'                 => $remote['email'] ?? null,
                'profile_image_url'     => $remote['avatar'] ?? null,
                'phone_number'          => $r['phone_number'],
                'gender'                => $r['gender'],
                'role_id'               => $r['role_id'],
                'role_name'             => $r['role_name'],
                'university_id'         => $r['university_id'],
                'university_name'       => $r['university_name'],
                'academic_year_id'      => $r['academic_year_id'],
                'academic_year'         => $r['academic_year'],
                'specialty_id'          => $r['specialty_id'],
                'specialty_name'        => $r['specialty_name'],
                'is_premium'            => (int) $r['is_premium'],
                'premium_until'         => $r['premium_until'],
                'is_complete'           => (int) ($r['is_complete'] ?? 0),
                'created_at'            => $r['created_at'],
                'last_login_at'         => $r['last_login_at'],
                'last_seen_at'          => $r['last_seen_at'],
                'deletion_scheduled_at' => $r['deletion_scheduled_at'],
            ];
        }

        return $this->respond([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'page' => $page, 'per_page' => $perPage, 'total' => $total,
                    'total_pages' => $totalPages,
                    'has_prev' => $page > 1, 'has_next' => $page < $totalPages,
                    'prev_page' => $page > 1 ? $page - 1 : null,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'first_page' => 1, 'last_page' => $totalPages,
                ],
            ],
        ]);
    }

    private function emptyListPayload(int $page, int $perPage): array
    {
        return [
            'success' => true,
            'data' => [
                'users' => [],
                'pagination' => [
                    'page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 1,
                    'has_prev' => false, 'has_next' => false, 'prev_page' => null, 'next_page' => null,
                    'first_page' => 1, 'last_page' => 1,
                ],
            ],
        ];
    }

    /** ترتيب آمن (whitelist). */
    private function resolveSort(string $sort): array
    {
        return match ($sort) {
            'created_asc'  => ['du.created_at', 'ASC'],
            'created_desc' => ['du.created_at', 'DESC'],
            'login_desc'   => ['du.last_login_at', 'DESC'],
            'login_asc'    => ['du.last_login_at', 'ASC'],
            'seen_desc'    => ['du.last_seen_at', 'DESC'],
            default        => ['du.id', 'DESC'],
        };
    }

    /**
     * فلاتر diploma_users المشتركة (تشمل التاريخ والنشاط والجدولة).
     * @param \CodeIgniter\Database\BaseBuilder $b
     */
    private function applyDiplomaFilters($b, $roleId, string $phone, ?array $remoteIdsFilter, $complete): void
    {
        if ($roleId !== null && $roleId !== '') $b->where('du.account_type_id', (int) $roleId);
        if ($phone !== '') $b->like('du.phone_number', $phone);
        if (is_array($remoteIdsFilter)) $b->whereIn('du.user_id', $remoteIdsFilter);
        if ($complete !== null && $complete !== '') {
            $b->where('du.is_complete', ((string) $complete === '1') ? 1 : 0);
        }

        // تاريخ التسجيل (من–إلى)
        $rf = trim((string) $this->request->getGet('registered_from'));
        $rt = trim((string) $this->request->getGet('registered_to'));
        if ($rf !== '') $b->where('du.created_at >=', $rf . ' 00:00:00');
        if ($rt !== '') $b->where('du.created_at <=', $rt . ' 23:59:59');

        // النشاط (حسب آخر نشاط أو آخر دخول)
        $activity = (string) $this->request->getGet('activity');
        $act = 'COALESCE(du.last_seen_at, du.last_login_at)';
        switch ($activity) {
            case 'active_7':    $b->where("{$act} >= (NOW() - INTERVAL 7 DAY)", null, false); break;
            case 'active_30':   $b->where("{$act} >= (NOW() - INTERVAL 30 DAY)", null, false); break;
            case 'inactive_30': $b->where("({$act} IS NULL OR {$act} < (NOW() - INTERVAL 30 DAY))", null, false); break;
            case 'inactive_90': $b->where("({$act} IS NULL OR {$act} < (NOW() - INTERVAL 90 DAY))", null, false); break;
            case 'never':       $b->where("du.last_login_at IS NULL", null, false); break;
        }

        // مجدول للحذف فقط
        if ((string) $this->request->getGet('scheduled') === '1') {
            $b->where('du.deletion_scheduled_at IS NOT NULL', null, false);
        }
    }

    /* ==================================================================== */
    public function options()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');

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

    /* ====================================================================
     *  إحصاءات غنية للوحة المعلومات
     * ==================================================================== */
    public function stats()
    {
        $db = Database::connect('default');

        // حسب الدور
        $rows = $db->table('diploma_users AS du')
            ->select('du.account_type_id, COALESCE(at.name, "Unknown") AS role_name, COUNT(*) AS cnt', false)
            ->join('account_types AS at', 'at.id = du.account_type_id', 'left')
            ->groupBy('du.account_type_id, at.name')
            ->get()->getResultArray();

        $byRole = []; $students = 0; $teachers = 0; $admins = 0; $trial = 0;
        foreach ($rows as $r) {
            $roleId = (int) ($r['account_type_id'] ?? 0);
            $count  = (int) ($r['cnt'] ?? 0);
            $byRole[] = ['role_id' => $roleId, 'role_name' => trim((string) ($r['role_name'] ?? '')), 'count' => $count];
            if ($roleId === 3) $students += $count;
            elseif ($roleId === 2) $teachers += $count;
            elseif ($roleId === 1) $admins += $count;
            elseif ($roleId === 4) $trial += $count;
        }

        $total = (int) $db->table('diploma_users')->countAllResults();
        $active7  = (int) $db->table('diploma_users')->where('COALESCE(last_seen_at, last_login_at) >= (NOW() - INTERVAL 7 DAY)', null, false)->countAllResults();
        $active30 = (int) $db->table('diploma_users')->where('COALESCE(last_seen_at, last_login_at) >= (NOW() - INTERVAL 30 DAY)', null, false)->countAllResults();
        $new30    = (int) $db->table('diploma_users')->where('created_at >= (NOW() - INTERVAL 30 DAY)', null, false)->countAllResults();
        $completed= (int) $db->table('diploma_users')->where('is_complete', 1)->countAllResults();
        $premium  = (int) $db->table('diploma_users')->where('is_premium', 1)
            ->where('(premium_until IS NULL OR premium_until >= NOW())', null, false)->countAllResults();
        $scheduled= (int) $db->table('diploma_users')->where('deletion_scheduled_at IS NOT NULL', null, false)->countAllResults();

        return $this->respond(['success' => true, 'data' => [
            'students' => $students, 'teachers' => $teachers, 'admins' => $admins, 'trial' => $trial,
            'total' => $total, 'active_7' => $active7, 'active_30' => $active30,
            'inactive_30' => max(0, $total - $active30), 'new_30' => $new30,
            'completed' => $completed, 'premium' => $premium, 'scheduled_deletion' => $scheduled,
            'by_role' => $byRole,
        ]]);
    }

    /* ====================================================================
     *  بيانات الرسوم البيانية (تسجيلات شهرية + دخول يومي + توزيع أدوار)
     * ==================================================================== */
    public function dashboard()
    {
        $db = Database::connect('default');

        // تسجيلات آخر 12 شهراً
        $reg = $db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
             FROM diploma_users
             WHERE created_at >= (NOW() - INTERVAL 12 MONTH)
             GROUP BY ym ORDER BY ym ASC"
        )->getResultArray();

        // دخول يومي آخر 30 يوماً (من rewards_daily_logins إن وُجد)
        $logins = [];
        if ($db->tableExists('rewards_daily_logins')) {
            $dateCol = $db->fieldExists('login_date', 'rewards_daily_logins') ? 'login_date'
                     : ($db->fieldExists('created_at', 'rewards_daily_logins') ? 'created_at' : null);
            if ($dateCol) {
                $logins = $db->query(
                    "SELECT DATE({$dateCol}) AS d, COUNT(*) AS c
                     FROM rewards_daily_logins
                     WHERE {$dateCol} >= (NOW() - INTERVAL 30 DAY)
                     GROUP BY d ORDER BY d ASC"
                )->getResultArray();
            }
        }

        return $this->respond(['success' => true, 'data' => [
            'registrations' => $reg,
            'daily_logins'  => $logins,
        ]]);
    }

    /* ====================================================================
     *  الحذف الفردي (يبقى للتوافق) — يطال الدبلوم + بياناته المرتبطة
     * ==================================================================== */
    public function delete()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');
        $data   = $this->request->getJSON(true);
        $userId = (int) ($data['user_id'] ?? 0);
        if (! $userId) return $this->failValidationErrors('معرّف المستخدم غير صالح');

        $n = $this->purgeUsers([$userId]);
        if ($n <= 0) return $this->failNotFound('المستخدم غير موجود في سجلات الدبلوم');
        return $this->respond(['success' => true, 'message' => 'تم حذف المستخدم وبياناته المرتبطة', 'deleted' => $n]);
    }

    /* ====================================================================
     *  حذف جماعي
     * ==================================================================== */
    public function bulkDelete()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');
        $ids = $this->incomingIds();
        if (empty($ids)) return $this->failValidationErrors('لم يتم تحديد مستخدمين');

        $deleted = $this->purgeUsers($ids);
        return $this->respond(['success' => true, 'message' => "تم حذف {$deleted} حساباً وبياناته المرتبطة", 'deleted' => $deleted]);
    }

    /** حذف فعلي للمستخدمين + بياناتهم في قاعدة الدبلوم (لا يمسّ حساب المصادقة). */
    private function purgeUsers(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
        if (empty($ids)) return 0;

        // إغلاق الحساب مركزيًا في Plans (إلغاء الاشتراكات + تصفير المحفظة) — best-effort
        try {
            $plansClient = new \App\Libraries\PlansClient();
            foreach ($ids as $uid) {
                $plansClient->accountClose($uid);
            }
        } catch (\Throwable $e) {
            log_message('error', 'purgeUsers: Plans accountClose failed: ' . $e->getMessage());
        }

        $db = Database::connect('default');
        foreach (self::USER_OWNED_TABLES as $tbl) {
            try {
                if ($db->tableExists($tbl) && $db->fieldExists('user_id', $tbl)) {
                    $db->table($tbl)->whereIn('user_id', $ids)->delete();
                }
            } catch (\Throwable $e) {
                log_message('error', "purgeUsers: clean {$tbl} failed: " . $e->getMessage());
            }
        }
        $db->table('diploma_users')->whereIn('user_id', $ids)->delete();
        return (int) $db->affectedRows();
    }

    /* ====================================================================
     *  جدولة حذف خلال 30 يوماً + إشعار المستخدمين
     * ==================================================================== */
    public function scheduleDeletion()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');
        $ids = $this->incomingIds();
        if (empty($ids)) return $this->failValidationErrors('لم يتم تحديد مستخدمين');

        $db  = Database::connect('default');
        $now = date('Y-m-d H:i:s');
        $due = date('Y-m-d H:i:s', strtotime('+30 days'));

        $db->table('diploma_users')->whereIn('user_id', $ids)->update([
            'deletion_scheduled_at' => $due,
            'deletion_notified_at'  => $now,
        ]);

        $title = 'تنبيه: سيتم حذف بياناتك';
        $msg   = 'سيتم حذف حسابك وبياناتك على المنصة خلال 30 يوماً من تاريخ هذا الإشعار. إذا رغبت بالاحتفاظ بحسابك، فقط سجّل الدخول واستخدم المنصة خلال هذه المدة.';
        $sent  = NotificationService::pushMany($ids, $title, $msg, '#/account', 9);

        return $this->respond([
            'success' => true,
            'message' => 'تم جدولة الحذف خلال 30 يوماً وإشعار المستخدمين',
            'scheduled' => count($ids), 'notified' => $sent, 'due_at' => $due,
        ]);
    }

    /** إلغاء جدولة الحذف. */
    public function cancelDeletion()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');
        $ids = $this->incomingIds();
        if (empty($ids)) return $this->failValidationErrors('لم يتم تحديد مستخدمين');

        Database::connect('default')->table('diploma_users')->whereIn('user_id', $ids)->update([
            'deletion_scheduled_at' => null,
            'deletion_notified_at'  => null,
        ]);
        return $this->respond(['success' => true, 'message' => 'تم إلغاء جدولة الحذف', 'cancelled' => count($ids)]);
    }

    /* ====================================================================
     *  سجل نشاط مستخدم
     * ==================================================================== */
    public function userActivity(int $userId)
    {
        if ($userId <= 0) return $this->failValidationErrors('معرّف غير صالح');
        $db = Database::connect('default');

        $du = $db->table('diploma_users')
            ->select('user_id, created_at, last_login_at, last_seen_at, is_premium, premium_until, is_complete, consent_given, consent_date, terms_accepted, terms_accepted_at, deletion_scheduled_at')
            ->where('user_id', $userId)->get()->getRowArray();
        if (! $du) return $this->failNotFound('المستخدم غير موجود');

        // الأجهزة
        $devices = [];
        if ($db->tableExists('user_devices')) {
            $devices = $db->table('user_devices')
                ->select('device_label, ip_address, user_agent, is_active, last_used_at, created_at')
                ->where('user_id', $userId)->orderBy('last_used_at', 'DESC')->limit(10)->get()->getResultArray();
        }

        // سجل الدخول اليومي (آخر 30)
        $logins = [];
        if ($db->tableExists('rewards_daily_logins')) {
            $dateCol = $db->fieldExists('login_date', 'rewards_daily_logins') ? 'login_date'
                     : ($db->fieldExists('created_at', 'rewards_daily_logins') ? 'created_at' : null);
            if ($dateCol) {
                $logins = $db->table('rewards_daily_logins')
                    ->select("DATE({$dateCol}) AS d", false)
                    ->where('user_id', $userId)
                    ->orderBy($dateCol, 'DESC')->limit(30)->get()->getResultArray();
            }
        }

        // الاسم/البريد من remote
        $remote = Database::connect('remote')->table('users')
            ->select('name, email, avatar')->where('id', $userId)->get()->getRowArray();

        return $this->respond(['success' => true, 'data' => [
            'profile'  => array_merge($du, ['name' => $remote['name'] ?? null, 'email' => $remote['email'] ?? null, 'avatar' => $remote['avatar'] ?? null]),
            'devices'  => $devices,
            'logins'   => $logins,
        ]]);
    }

    /* ====================================================================
     *  تصدير CSV للمفلترين/المحدّدين
     * ==================================================================== */
    public function exportCsv()
    {
        $db = Database::connect('default');
        // المعرّفات عبر الكويري (GET): ?ids=1,2,3 — إن مُرّرت تُصدَّر المحددة فقط
        $idsRaw = (string) $this->request->getGet('ids');
        $ids = $idsRaw !== ''
            ? array_values(array_unique(array_filter(array_map('intval', explode(',', $idsRaw)), static fn ($v) => $v > 0)))
            : [];
        $b = $db->table('diploma_users AS du')
            ->select('du.user_id, du.phone_number, du.account_type_id, at.name AS role_name, du.is_premium, du.is_complete, du.created_at, du.last_login_at, du.last_seen_at')
            ->join('account_types AS at', 'at.id = du.account_type_id', 'left')
            ->orderBy('du.id', 'DESC');
        if (! empty($ids)) $b->whereIn('du.user_id', $ids);
        $rows = $b->get(5000)->getResultArray();

        $remoteMap = [];
        $uids = array_column($rows, 'user_id');
        if (! empty($uids)) {
            foreach (Database::connect('remote')->table('users')->select('id, name, email')->whereIn('id', $uids)->get()->getResultArray() as $rr) {
                $remoteMap[(int) $rr['id']] = $rr;
            }
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['user_id', 'name', 'email', 'phone', 'role', 'premium', 'complete', 'registered', 'last_login', 'last_seen']);
        foreach ($rows as $r) {
            $rid = (int) $r['user_id'];
            $rm  = $remoteMap[$rid] ?? [];
            fputcsv($out, [
                $rid, $rm['name'] ?? '', $rm['email'] ?? '', $r['phone_number'] ?? '',
                $r['role_name'] ?? '', (int) $r['is_premium'], (int) $r['is_complete'],
                $r['created_at'] ?? '', $r['last_login_at'] ?? '', $r['last_seen_at'] ?? '',
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="users_export_' . date('Ymd_His') . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv); // BOM لدعم العربية في Excel
    }

    /* ==================================================================== */
    public function updateAccount()
    {
        if (! $this->request->isAJAX()) return $this->failForbidden('Access denied');
        $data = $this->request->getJSON(true);

        $userId = (int) ($data['user_id'] ?? 0);
        if (! $userId) return $this->failValidationErrors('معرّف المستخدم غير صالح');

        $toNullInt = static fn($v) => ($v === '' || $v === null) ? null : (int) $v;
        $updateData = [
            'account_type_id'  => $toNullInt($data['role_id'] ?? null),
            'university_id'    => $toNullInt($data['university_id'] ?? null),
            'academic_year_id' => $toNullInt($data['academic_year_id'] ?? null),
            'specialty_id'     => $toNullInt($data['specialty_id'] ?? null),
        ];

        $db  = Database::connect('default');
        $row = $db->table('diploma_users')->where('user_id', $userId)->get()->getRowArray();
        try {
            if ($row) {
                $db->table('diploma_users')->where('user_id', $userId)->update($updateData);
            } else {
                $updateData['user_id'] = $userId;
                $db->table('diploma_users')->insert($updateData);
            }
            return $this->respond(['success' => true, 'message' => 'تم حفظ التعديلات بنجاح']);
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('فشل حفظ البيانات');
        }
    }

    /** قراءة قائمة user_ids من جسم الطلب (JSON: {user_ids:[...]}) أو user_id مفرد. */
    private function incomingIds(): array
    {
        $data = $this->request->getJSON(true) ?: [];
        $ids  = $data['user_ids'] ?? ($data['user_id'] ?? []);
        if (! is_array($ids)) $ids = [$ids];
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
    }
}
