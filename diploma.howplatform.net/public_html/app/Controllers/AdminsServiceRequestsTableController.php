<?php

namespace App\Controllers;

use App\Models\ServiceRequestModel;
use App\Models\ServiceRequestMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\UserModel;

class AdminsServiceRequestsTableController extends BaseController
{
    /** @var int[] الأدوار المسموح لها بدخول الصفحة وقراءة البيانات */
    private const PAGE_ALLOWED_ROLES = [1, 2, 4]; // 1=admin, 2=teacher, 4=trial

    /** @var string[] جميع الحالات المسموح بها */
    private const VALID_STATUSES = ['pending', 'in_progress', 'resolved', 'closed', 'cancelled'];

    private ServiceRequestModel $serviceRequestModel;
    private ServiceRequestMessageModel $messageModel;
    private ServiceRequestFileModel $fileModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->serviceRequestModel = new ServiceRequestModel();
        $this->messageModel        = new ServiceRequestMessageModel();
        $this->fileModel           = new ServiceRequestFileModel();
        $this->userModel           = new UserModel();
    }

    /**
     * ✅ مساعد: قراءة المدخلات من JSON أو POST
     */
    private function input(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && ! empty($json)) {
            return $json;
        }

        return $this->request->getPost() ?? [];
    }

    /**
     * عرض الصفحة (إن كانت تستخدم ريندر تقليدي)
     */
    public function index()
    {
        if (! $this->isPageAllowed()) {
            return redirect()->to('/login');
        }

        $data = [
            'title'          => 'إدارة طلبات الدعم - الجدول',
            'active_section' => 'service_requests_table',
        ];

        return view('admins/pages/service_requests', $data);
    }

    /**
     * API: قائمة الطلبات مع الفلاتر والترقيم
     */
    public function getRequestsList()
    {
        if (! $this->request->isAJAX() || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $currentUserId = $this->getCurrentUserId();
        $role          = $this->getCurrentUserRole();

        // ⚖️ فلاتر GET (بدون filter_var)
        $statusRaw   = (string) ($this->request->getGet('status') ?? '');
        $status      = $this->normalizeStatus($statusRaw); // null لو غير صالح

        $assigned    = (string) ($this->request->getGet('assigned') ?? '');
        $qRaw        = $this->request->getGet('q');
        $q           = trim((string) ($qRaw ?? ''));

        $pageRaw     = $this->request->getGet('page');
        $perPageRaw  = $this->request->getGet('per_page');

        $page        = max(1, (int) ($pageRaw ?? 1));
        $perPage     = max(5, min(50, (int) ($perPageRaw ?? 10)));

        // 🧭 نطاق البيانات حسب فلتر التكليف
        $targetAdminId  = null;
        $unassignedOnly = false;

        if ($assigned === 'me') {
            $targetAdminId = $currentUserId;
        } elseif ($assigned === 'unassigned') {
            $unassignedOnly = true;
        } elseif (is_numeric($assigned) && $role === 1) {
            // الأدمن يستطيع رؤية طلبات مستخدم معيّن
            $targetAdminId = (int) $assigned;
        }

        // 🔢 إجمالي وعدد صفحات
        $total    = $this->serviceRequestModel->countForAdmin($targetAdminId, $status, $unassignedOnly, $q !== '' ? $q : null);
        $offset   = ($page - 1) * $perPage;

        // 📥 البيانات
        $requests = $this->serviceRequestModel->getForAdminPaginated(
            $targetAdminId,
            $status,
            $unassignedOnly,
            $q !== '' ? $q : null,
            $perPage,
            $offset
        );

        // 🔔 رسائل غير مقروءة لكل طلب للمستخدم الحالي
        $requestIds   = array_column($requests, 'id');
        $unreadCounts = $this->getUnreadCountsForRequests($requestIds, $currentUserId);

        foreach ($requests as &$r) {
            $r['unread_count'] = $unreadCounts[$r['id']] ?? 0;
        }

        // 📊 إحصائيات
        $stats = $this->getTableStats($currentUserId, $role);
        
                return $this->response->setJSON([
            'success'    => true,
            'data'       => $requests,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => (int) $total,
                'pages'    => (int) ceil($total / $perPage),
            ],
            'stats' => $stats,
            'current_user' => [
                'id'       => (int) $currentUserId,
                'role'     => (int) $role,
                'is_admin' => $this->isAdminRole(),
            ],
        ]);

    }

    /**
     * ✅ تحديث حالة الطلب — مسموح للأدوار 1,2,4
     * - لا يحق لغير الأدمن: تعديل طلب حالته الحالية "cancelled"
     * - لا يحق لغير الأدمن: تغيير الحالة إلى "cancelled"
     */
    public function updateStatus($requestId = null)
    {
        if (! $this->request->isAJAX() || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $data      = $this->input();
        $requestId = $requestId ?: ($data['request_id'] ?? null);
        $newStatus = isset($data['status']) ? $this->normalizeStatus((string) $data['status']) : null;

        if (! $requestId || ! $newStatus) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات ناقصة/حالة غير صالحة']);
        }

        $request = $this->serviceRequestModel->getRequestWithDetails($requestId);
        if (! $request) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
        }

        $role = $this->getCurrentUserRole();

        // ⛔ منع غير الأدمن من التعديل إذا كانت الحالة الحالية "cancelled"
        if ($request['status'] === 'cancelled' && $role !== 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يمكنك تعديل طلب ملغي.']);
        }

        // ⛔ منع غير الأدمن من تعيين الحالة إلى "cancelled"
        if ($newStatus === 'cancelled' && $role !== 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يحق لك تعيين الحالة إلى ملغي.']);
        }

        // ✅ التعديل مسموح للأدوار 1/2/4 لباقي الحالات
        if (! $this->canModifyRequest($request)) {
            return $this->response->setJSON(['success' => false, 'message' => 'ليس لديك صلاحية تعديل هذا الطلب']);
        }

        $actorId = $this->getCurrentUserId();
        $ok      = $this->serviceRequestModel->updateStatus($requestId, $newStatus, $actorId);

        if ($ok) {
            $this->addSystemMessage($requestId, 'تم تغيير حالة الطلب إلى: ' . $this->getStatusText($newStatus));
            return $this->response->setJSON(['success' => true, 'message' => 'تم تحديث حالة الطلب بنجاح']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'فشل في تحديث الحالة']);
    }

    /**
     * ✅ تكليف الطلب — Admin فقط (ويمكن التكليف إلى Admin/Teacher)
     */
    public function assignRequest($requestId = null)
    {
        if (! $this->request->isAJAX() || ! $this->isAdminRole()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $data      = $this->input();
        $requestId = $requestId ?: ($data['request_id'] ?? null);
        $adminId   = $data['admin_id'] ?? null;

        if (! $requestId || ! $adminId) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات ناقصة']);
        }

        $request = $this->serviceRequestModel->find($requestId);
        $admin   = $this->userModel->find($adminId);

        if (! $request || ! $admin) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب أو المستخدم غير موجود']);
        }

        // ✅ المسموح التكليف إليهم: Admin (1) أو Teacher (2)
        if (! in_array((int) $admin['account_type_id'], [1, 2], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'المستخدم المحدد ليس Admin/Teacher']);
        }

        $ok = $this->serviceRequestModel->assignToAdmin($requestId, $adminId);

        if ($ok) {
            $this->addSystemMessage($requestId, 'تم تكليف الطلب للمستخدم: ' . ($admin['name'] ?? ('#' . $adminId)));
            return $this->response->setJSON(['success' => true, 'message' => 'تم تكليف الطلب بنجاح']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'فشل في تكليف الطلب']);
    }

    /**
     * ✅ قائمة المكلّفين المحتملين — Admin فقط
     */
    public function getAdminsList()
    {
        if (! $this->request->isAJAX() || ! $this->isAdminRole()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $admins = $this->userModel
            ->select('id, name, email')
            ->whereIn('account_type_id', [1, 2]) // Admin & Teacher
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->response->setJSON(['success' => true, 'data' => $admins]);
    }

    /**
     * ✅ إحصائيات الجدول — للأدوار 1,2,4
     */
    public function getStats()
    {
        if (! $this->request->isAJAX() || ! $this->isPageAllowed()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $stats = $this->getTableStats($this->getCurrentUserId(), $this->getCurrentUserRole());

        return $this->response->setJSON(['success' => true, 'data' => $stats]);
    }

    // ==================== Helpers ====================

    private function getTableStats(int $adminId, int $adminRole): array
    {
        $allStats   = $this->serviceRequestModel->getAdminStats();         // عام
        $myStats    = $this->serviceRequestModel->getAdminStats($adminId); // يخص المستخدم الحالي
        $unassigned = $this->serviceRequestModel->countForAdmin(null, null, true);

        return [
            'all_requests' => $allStats,
            'my_requests'  => $myStats,
            'unassigned'   => $unassigned,
        ];
    }

    private function getUnreadCountsForRequests(array $requestIds, int $adminId): array
    {
        if (empty($requestIds)) {
            return [];
        }

        $counts = [];
        foreach ($requestIds as $requestId) {
            $counts[$requestId] = $this->messageModel->getUnreadCountForAdmin($requestId, $adminId);
        }
        return $counts;
    }

    private function addSystemMessage(int $requestId, string $message): void
    {
        $this->messageModel->addAdminMessage([
            'service_request_id' => $requestId,
            'sender_id'          => $this->getCurrentUserId(),
            'message_type'       => 'text',
            'message'            => $message,
            'is_read'            => 0,
        ]);
    }

    /**
     * من يحق له تعديل/تغيير حالة الطلب؟
     * الآن: Admin + Teacher + Trial (مع قيود "cancelled" أعلاه)
     */
    private function canModifyRequest(array $request): bool
    {
        return in_array($this->getCurrentUserRole(), [1, 2, 4], true);
    }

    private function getStatusText(string $status): string
    {
        $map = [
            'pending'     => 'في الانتظار',
            'in_progress' => 'قيد المعالجة',
            'resolved'    => 'تم الحل',
            'closed'      => 'مغلق',
            'cancelled'   => 'ملغي',
        ];

        return $map[$status] ?? $status;
    }

    private function normalizeStatus(?string $status): ?string
    {
        $s = trim((string) $status);
        if ($s === '' || ! in_array($s, self::VALID_STATUSES, true)) {
            return null;
        }
        return $s;
    }

    // -------- الصلاحيات العامة --------
    private function isPageAllowed(): bool
    {
        return in_array($this->getCurrentUserRole(), self::PAGE_ALLOWED_ROLES, true);
    }

    private function isAdminRole(): bool
    {
        return $this->getCurrentUserRole() === 1; // Admin فقط
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
        return (int) (session('role') ?? 0); // ✅ تذكير: تُخزّن في الجلسة بـ 'role'
    }
}
