<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\ServiceRequestModel;
use App\Models\DiplomaUserModel;
use App\Libraries\RemoteUsersClient;

class AdminServiceRequestsTableController extends BaseController
{
    /** @var int[] الأدوار المسموح لها بدخول الصفحة وقراءة البيانات */
    private const PAGE_ALLOWED_ROLES = [1, 2, 4]; // 1=admin, 2=teacher, 4=trial

    /** @var string[] جميع الحالات المسموح بها */
    private const VALID_STATUSES = ['pending', 'in_progress', 'resolved', 'closed', 'cancelled'];

    private ServiceRequestModel $serviceRequestModel;
    private DiplomaUserModel $diplomaUserModel;
    private RemoteUsersClient $remoteUsers;

    public function __construct()
    {
        $this->serviceRequestModel = new ServiceRequestModel();
        $this->diplomaUserModel    = new DiplomaUserModel();
        $this->remoteUsers         = new RemoteUsersClient();
    }

    /* ===================== صفحات ===================== */

    public function index()
    {
        if (! $this->isPageAllowed()) {
            return redirect()->to('/login');
        }

        $data = [
            'title'          => 'إدارة طلبات المراسلة - الجدول',
            'active_section' => 'service_requests_table',
        ];

        return view('admins/pages/service_requests', $data);
    }

    public function health()
    {
        return $this->response->setJSON([
            'ok'          => true,
            'controller'  => self::class,
            'method'      => __FUNCTION__,
            'user_id'     => $this->getCurrentUserId(),
            'role'        => $this->getCurrentUserRole(),
            'ts'          => date('Y-m-d H:i:s'),
            'request_uri' => (string) current_url(),
            'ajax'        => $this->request->isAJAX(),
        ]);
    }

    /* ===================== API: الإحصائيات ===================== */

    public function getStats()
    {
        $isAjax = $this->request->isAJAX() || $this->request->getGet('xhr') === '1';
        if (! $isAjax || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $currentUserId = $this->getCurrentUserId();
        $role          = $this->getCurrentUserRole();

        // إجماليات عامة
        $all  = $this->serviceRequestModel->getStatusBuckets();
        // إجماليات المستخدم الحالي
        $mine = $currentUserId > 0
            ? $this->serviceRequestModel->getStatusBuckets(['assigned_to' => $currentUserId])
            : ['total'=>0,'pending'=>0,'in_progress'=>0,'resolved'=>0,'closed'=>0,'cancelled'=>0];

        $unassigned = $this->serviceRequestModel->countUnassigned();

        // ✅ لغير الأدمن: لا نُظهر سوى أرقام تخصّه
        if ($role !== 1) {
            $all        = $mine;
            $unassigned = 0;
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'all_requests' => $all,
                'my_requests'  => $mine,
                'unassigned'   => $unassigned,
            ],
        ]);
    }

    /* ===================== API: قائمة الطلبات ===================== */

    public function getRequestsList()
    {
        $isAjax = $this->request->isAJAX() || $this->request->getGet('xhr') === '1';
        if (! $isAjax || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $currentUserId = $this->getCurrentUserId();
        $role          = $this->getCurrentUserRole();

        // ===== فلاتر =====
        $statusRaw = (string) ($this->request->getGet('status') ?? '');
        $status    = $this->normalizeStatus($statusRaw);

        $assigned  = (string) ($this->request->getGet('assigned') ?? '');
        $q         = trim((string) ($this->request->getGet('q') ?? ''));

        $page      = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage   = max(5, min(50, (int) ($this->request->getGet('per_page') ?? 10)));
        $offset    = ($page - 1) * $perPage;

        // نطاق التكليف
        $targetAdminId  = null;   // user_id من diploma_users
        $unassignedOnly = false;

        if ($role === 1) {
            // ✅ أدمن: يطبّق الفلاتر كما أرسلها
            if ($assigned === 'me') {
                $targetAdminId = $currentUserId;
            } elseif ($assigned === 'unassigned') {
                $unassignedOnly = true;
            } elseif (is_numeric($assigned)) {
                $targetAdminId = (int) $assigned;
            }
        } else {
            // ✅ غير الأدمن: يرى فقط ما هو مكلّف به
            $targetAdminId  = $currentUserId;
            $unassignedOnly = false;
        }

        // ======= العدّ الإجمالي بنفس الفلاتر =======
        $db = $this->serviceRequestModel->db;

        $countBuilder = $db->table('service_requests sr');
        if ($status !== null) {
            $countBuilder->where('sr.status', $status);
        }
        if ($targetAdminId !== null) {
            $countBuilder->where('sr.assigned_to', $targetAdminId);
        } elseif ($unassignedOnly) {
            $countBuilder->groupStart()
                ->where('sr.assigned_to IS NULL', null, false)
                ->orWhere('sr.assigned_to', 0)
            ->groupEnd();
        }
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('sr.subject', $q)
                ->orLike('sr.description', $q)
            ->groupEnd();
        }

        $total = (int) $countBuilder->countAllResults();

        // ======= البيانات + اسم المساق + unread =======
        $viewerId   = (int) $currentUserId;
        $unreadExpr = "(SELECT COUNT(*)
                         FROM service_messages sm
                         WHERE sm.service_request_id = sr.id
                           AND sm.sender_user_id != {$viewerId}
                           AND NOT EXISTS (
                                 SELECT 1 FROM service_message_reads r
                                  WHERE r.message_id = sm.id
                                    AND r.reader_user_id = {$viewerId}
                               )
                       ) AS unread_count";

        $b = $db->table('service_requests sr')
            ->select("
                sr.id, sr.user_id, sr.course_offering_id,
                sr.subject, sr.description,
                sr.status, sr.priority,
                sr.assigned_to,
                sr.created_at, sr.updated_at,
                COALESCE(c.name, '') AS course_name,
                {$unreadExpr}
            ", false)
            ->join('course_offerings co', 'co.id = sr.course_offering_id', 'left')
            ->join('courses c', 'c.id = co.course_id', 'left');

        if ($status !== null) {
            $b->where('sr.status', $status);
        }
        if ($targetAdminId !== null) {
            $b->where('sr.assigned_to', $targetAdminId);
        } elseif ($unassignedOnly) {
            $b->groupStart()
                ->where('sr.assigned_to IS NULL', null, false)
                ->orWhere('sr.assigned_to', 0)
            ->groupEnd();
        }
        if ($q !== '') {
            $b->groupStart()
                ->like('sr.subject', $q)
                ->orLike('sr.description', $q)
            ->groupEnd();
        }

        $b->orderBy('COALESCE(sr.updated_at, sr.created_at)', 'DESC', false)
          ->limit($perPage, $offset);

        $rows = $b->get()->getResultArray();

        // ======= جلب الأسماء (طالب/مكلّف) من خدمة الأسماء =======
        $lookupIds   = [];
        $assignedIds = [];
        foreach ($rows as $r) {
            if (! empty($r['user_id'])) {
                $lookupIds[] = (int) $r['user_id'];
            }
            if (! empty($r['assigned_to'])) {
                $lookupIds[]   = (int) $r['assigned_to'];
                $assignedIds[] = (int) $r['assigned_to'];
            }
        }
        $lookupIds    = array_values(array_unique(array_filter($lookupIds)));
        $assignedIds  = array_values(array_unique(array_filter($assignedIds)));
        $namesMap     = ! empty($lookupIds) ? $this->remoteUsers->resolveMany($lookupIds) : [];

        // اسم نوع الحساب للمكلّف (قراءة فقط)
        $accountTypeMap = [];
        if (! empty($assignedIds)) {
            $roleRows = $this->diplomaUserModel
                ->select('diploma_users.user_id, account_types.name AS account_type_name')
                ->join('account_types', 'account_types.id = diploma_users.account_type_id', 'left')
                ->whereIn('diploma_users.user_id', $assignedIds)
                ->findAll();

            foreach ($roleRows as $rr) {
                $uid = (int) ($rr['user_id'] ?? 0);
                if ($uid > 0) {
                    $accountTypeMap[$uid] = (string) ($rr['account_type_name'] ?? '');
                }
            }
        }

        foreach ($rows as &$r) {
            $uid = (int) ($r['user_id'] ?? 0);
            $aid = (int) ($r['assigned_to'] ?? 0);

            $rq = $namesMap[$uid]['name'] ?? '';
            $ad = $namesMap[$aid]['name'] ?? '';

            $r['requester_name']       = (string) $rq;
            $r['assigned_admin_name']  = (string) $ad;
            $r['requester_email']      = '';
            $r['assigned_admin_email'] = '';

            $accTypeName = $accountTypeMap[$aid] ?? '';
            $r['assigned_admin_account_type'] = $accTypeName;
            $r['assigned_admin_label']        = trim($ad) !== '' && trim($accTypeName) !== ''
                ? ($ad . ' — ' . $accTypeName)
                : ($ad ?: '');
        }
        unset($r);

        // إحصائيات الكارد (تطبيق التقييد لغير الأدمن كما في getStats)
        $allStats  = $this->serviceRequestModel->getStatusBuckets();
        $myStats   = $this->serviceRequestModel->getStatusBuckets(['assigned_to' => $currentUserId]);
        $statsResp = [
            'all_requests' => ($role === 1) ? $allStats : $myStats,
            'my_requests'  => $myStats,
            'unassigned'   => ($role === 1) ? $this->serviceRequestModel->countUnassigned() : 0,
        ];

        return $this->response->setJSON([
            'success' => true,
            'data'    => $rows,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) max(1, ceil($total / $perPage)),
            ],
            'stats' => $statsResp,
            'current_user' => [
                'id'       => (int) $currentUserId,
                'role'     => (int) $role,
                'is_admin' => $this->isAdminRole(),
            ],
        ]);
    }

    /* ===================== API: قائمة الأدمنز للتكليف ===================== */

    public function getAdminsList()
    {
        $isAjax = $this->request->isAJAX() || $this->request->getGet('xhr') === '1';
        if (! $isAjax || ! $this->isAdminRole()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        // account_type_id != 3
        $rows = $this->diplomaUserModel->getAssignableAdmins();

        $ids      = array_values(array_unique(array_map(static fn(array $r) => (int) ($r['user_id'] ?? 0), $rows)));
        $namesMap = ! empty($ids) ? $this->remoteUsers->resolveMany($ids) : [];

        $data = array_map(static function (array $r) use ($namesMap): array {
            $id       = (int) ($r['user_id'] ?? 0);
            $name     = (string) ($namesMap[$id]['name'] ?? '');
            $typeName = (string) ($r['account_type_name'] ?? '');
            $label    = $typeName !== '' ? ($name . ' — ' . $typeName) : $name;

            return [
                'id'                => (string) $id,
                'name'              => $name,
                'email'             => '',
                'account_type_name' => $typeName,
                'label'             => $label,
            ];
        }, $rows);

        return $this->response->setJSON(['success' => true, 'data' => $data]);
    }

    /* ===================== API: تحديث الحالة ===================== */

    public function updateStatus($requestId = null)
    {
        $isAjax = $this->request->isAJAX() || $this->request->getGet('xhr') === '1';
        if (! $isAjax || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $data      = $this->getInput();
        $requestId = $requestId ?: (int) ($data['request_id'] ?? 0);
        $newStatus = isset($data['status']) ? $this->normalizeStatus((string) $data['status']) : null;

        if ($requestId <= 0 || ! $newStatus) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات ناقصة/حالة غير صالحة']);
        }

        $request = $this->serviceRequestModel->find($requestId);
        if (! $request) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
        }

        $role = $this->getCurrentUserRole();
        if ($request['status'] === 'cancelled' && $role !== 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يمكنك تعديل طلب ملغي.']);
        }
        if ($newStatus === 'cancelled' && $role !== 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يحق لك تعيين الحالة إلى ملغي.']);
        }

        $ok = $this->serviceRequestModel->updateStatus((int) $requestId, $newStatus, $this->getCurrentUserId());
        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'message' => 'فشل في تحديث الحالة']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'تم تحديث حالة الطلب بنجاح']);
    }

    /* ===================== API: التكليف ===================== */

    public function assignRequest($requestId = null)
    {
        $isAjax = $this->request->isAJAX() || $this->request->getGet('xhr') === '1';
        if (! $isAjax || ! $this->isAdminRole()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $data      = $this->getInput();
        $requestId = $requestId ?: (int) ($data['request_id'] ?? 0);
        $adminId   = (int) ($data['admin_id'] ?? 0); // user_id من diploma_users

        if ($requestId <= 0 || $adminId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات ناقصة']);
        }

        // تحقّق من أهلية المكلَّف: ليس طالبًا
        $isAssignable = (bool) $this->diplomaUserModel
            ->where('user_id', $adminId)
            ->where('account_type_id !=', 3)
            ->countAllResults();

        if (! $isAssignable) {
            return $this->response->setJSON(['success' => false, 'message' => 'المستخدم المحدد غير مؤهّل للتكليف (يجب أن يكون دوره != 3).']);
        }

        // نقرأ الطلب لمعرفة حالته الحالية
        $request = $this->serviceRequestModel->find($requestId);
        if (! $request) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
        }

        // تنفيذ ضمن معاملة: التكليف + تعديل الحالة عند الحاجة
        $db = $this->serviceRequestModel->db;
        $db->transStart();

        $ok1 = $this->serviceRequestModel->assignToAdmin((int) $requestId, (int) $adminId);
        $ok2 = true;

        // ✅ حالة افتراضية: pending → in_progress بعد التكليف
        if ($ok1 && ($request['status'] ?? '') === 'pending') {
            $ok2 = $this->serviceRequestModel->updateStatus((int) $requestId, 'in_progress', $this->getCurrentUserId());
        }

        $db->transComplete();

        if (! $db->transStatus() || ! $ok1 || ! $ok2) {
            return $this->response->setJSON(['success' => false, 'message' => 'فشل في تكليف الطلب/تحديث حالته']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'تم تكليف الطلب بنجاح']);
    }

    /* ===================== أدوات مساعدة ===================== */

    private function getInput(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && ! empty($json)) {
            return $json;
        }
        return $this->request->getPost() ?? [];
    }

    private function normalizeStatus(?string $status): ?string
    {
        $s = trim((string) $status);
        if ($s === '' || ! in_array($s, self::VALID_STATUSES, true)) {
            return null;
        }
        return $s;
    }

    private function isPageAllowed(): bool
    {
        return in_array($this->getCurrentUserRole(), self::PAGE_ALLOWED_ROLES, true);
    }

    private function isAdminRole(): bool
    {
        return $this->getCurrentUserRole() === 1;
    }

    private function getCurrentUserId(): int
    {
        return (int) (session('user_id') ?? 0);
    }

    /**
     * الدور يُقرأ من الجلسة كرقم: 1=admin, 2=teacher, 3=student, 4=trial
     */
    private function getCurrentUserRole(): int
    {
        return (int) (session('role') ?? 0);
    }
}
