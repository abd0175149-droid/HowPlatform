<?php

namespace App\Controllers;

use App\Models\ServiceRequestModel;
use App\Models\ServiceRequestMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\FilesModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class AdminServiceRequestsChatController extends BaseController
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    /** @var ServiceRequestModel */
    private $serviceRequestModel;

    /** @var ServiceRequestMessageModel */
    private $messageModel;

    /** @var ServiceRequestFileModel */
    private $fileModel;

    /** @var FilesModel */
    private $filesModel;

    /** @var UserModel */
    private $userModel;

    public function __construct()
    {
        $this->db                  = \Config\Database::connect();
        $this->serviceRequestModel = new ServiceRequestModel();
        $this->messageModel        = new ServiceRequestMessageModel();
        $this->fileModel           = new ServiceRequestFileModel();
        $this->filesModel          = new FilesModel();
        $this->userModel           = new UserModel();
    }

    /** صفحة المحادثة */
    public function index()
    {
        if (!$this->isAdmin()) return redirect()->to('/login');

        $data = [
            'title'          => 'محادثات طلبات الدعم',
            'active_section' => 'service_requests_chat',
        ];
        return view('admins/pages/service_chat', $data);
    }

    /** إحصائيات */
    public function stats()
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        try {
            $adminId = $this->getCurrentUserId();

            if ($this->isSuperAdmin()) {
                $data = $this->getChatStats($adminId, $this->getCurrentUserRole());
            } else {
                $mine = $this->serviceRequestModel->getAdminStats($adminId);
                $data = ['all' => $mine, 'assigned_to_me' => $mine, 'unassigned' => 0];
            }

            return $this->response->setJSON(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            log_message('error', 'Stats error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في تحميل الإحصائيات']);
        }
    }

    /** قائمة الطلبات (+ فلترة + صفحات + آخر رسالة) */
    public function getRequestsList()
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        try {
            $adminId   = (int) $this->getCurrentUserId();
            $assigned  = (string) $this->request->getGet('assigned'); // all | me | unassigned
            $status    = (string) $this->request->getGet('status');   // pending | in_progress | ...
            $limit     = (int) ($this->request->getGet('limit') ?? 20);
            $beforeId  = (int) ($this->request->getGet('before_id') ?? 0);

            $limit = max(1, min(100, $limit));

            // جلب الطلبات مع بيانات الطالب (requester) واسم الأدمن المكلّف
            $b = $this->db->table('service_requests sr')
                ->select("
                    sr.id,
                    sr.user_id,
                    sr.subject,
                    sr.status,
                    sr.priority,
                    sr.created_at,
                    sr.updated_at,
                    sr.assigned_to,
                    requester.name AS requester_name,
                    requester.email AS requester_email,
                    admin.name AS assigned_admin_name
                ")
                ->join('users requester', 'requester.id = sr.user_id', 'left')
                ->join('users admin', 'admin.id = sr.assigned_to', 'left');

            // فلتر الحالة
            $allowedStatuses = ['pending','in_progress','resolved','closed','cancelled'];
            if ($status && in_array($status, $allowedStatuses, true)) {
                $b->where('sr.status', $status);
            }

            // فلتر التكليف
            if ($this->isSuperAdmin()) {
                if ($assigned === 'me') {
                    $b->where('sr.assigned_to', $adminId);
                } elseif ($assigned === 'unassigned') {
                    $b->groupStart()
                        ->where('sr.assigned_to IS NULL', null, false)
                        ->orWhere('sr.assigned_to', 0)
                    ->groupEnd();
                }
            } else {
                $b->where('sr.assigned_to', $adminId);
            }

            if ($beforeId > 0) {
                $b->where('sr.id <', $beforeId);
            }

            // نجيب limit+1 لنعرف هل يوجد المزيد
            $rows = $b->orderBy('sr.id', 'DESC')
                      ->limit($limit + 1)
                      ->get()
                      ->getResultArray();

            $hasMore = false;
            if (count($rows) > $limit) {
                $hasMore = true;
                $rows = array_slice($rows, 0, $limit);
            }

            // لو لا يوجد طلبات
            if (empty($rows)) {
                $stats = $this->isSuperAdmin()
                    ? $this->getChatStats($adminId, $this->getCurrentUserRole())
                    : (function () use ($adminId) {
                        $mine = $this->serviceRequestModel->getAdminStats($adminId);
                        return ['all' => $mine, 'assigned_to_me' => $mine, 'unassigned' => 0];
                    })();

                return $this->response->setJSON(['success' => true, 'data' => [], 'has_more' => false, 'stats' => $stats]);
            }

            // unread counts
            $requestIds   = array_map('intval', array_column($rows, 'id'));
            $unreadCounts = $this->getUnreadCountsForRequests($requestIds, $adminId);

            // آخر رسالة لكل طلب
            $idsList = implode(',', $requestIds);
            $sqlLast = "
                SELECT m.service_request_id, m.id, m.message, m.message_type, m.created_at, m.sender_id,
                       u.name AS sender_name
                FROM service_request_messages m
                LEFT JOIN users u ON u.id = m.sender_id
                INNER JOIN (
                    SELECT service_request_id, MAX(id) AS max_id
                    FROM service_request_messages
                    WHERE service_request_id IN ($idsList)
                    GROUP BY service_request_id
                ) t ON t.service_request_id = m.service_request_id AND t.max_id = m.id
            ";
            $lastRows = $this->db->query($sqlLast)->getResultArray();
            $lastMap  = [];
            foreach ($lastRows as $lr) {
                $lastMap[(int) $lr['service_request_id']] = $lr;
            }

            // Helper معاينة نص أول 10 كلمات
            $makeWordsPreview = static function (?string $text, int $maxWords = 10): string {
                $text = trim((string) strip_tags($text ?? ''));
                if ($text === '') return '';
                $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($words) > $maxWords) {
                    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
                }
                return $text;
            };

            // دمج البيانات الإضافية
            foreach ($rows as &$r) {
                $rid = (int) $r['id'];
                $r['unread_count'] = $unreadCounts[$rid] ?? 0;

                $lm = $lastMap[$rid] ?? null;
                if ($lm) {
                    $type       = strtolower((string) ($lm['message_type'] ?? 'text'));
                    $senderId   = (int) ($lm['sender_id'] ?? 0);
                    $senderName = ($senderId === (int) $adminId)
                        ? 'أنت'
                        : (!empty($lm['sender_name']) ? $lm['sender_name'] : 'مستخدم');
                    if ($type === 'voice') {
                        $preview = "{$senderName}: تسجيل صوتي";
                    } elseif ($type === 'file') {
                        $preview = "{$senderName}: تم إرفاق ملف";
                    } else {
                        $preview = "{$senderName}: " . $makeWordsPreview($lm['message'] ?? '', 10);
                    }

                    $r['last_message'] = [
                        'id'          => (int) $lm['id'],
                        'type'        => $type,
                        'text'        => (string) ($lm['message'] ?? ''),
                        'sender_id'   => $senderId,
                        'sender_name' => $senderName,
                        'created_at'  => $lm['created_at'] ?? null,
                        'preview'     => $preview,
                    ];
                    $r['last_activity_at'] = $lm['created_at'] ?? ($r['updated_at'] ?? $r['created_at'] ?? null);
                } else {
                    $r['last_message']     = null;
                    $r['last_activity_at'] = $r['updated_at'] ?? $r['created_at'] ?? null;
                }
            }
            unset($r);

            // الإحصاءات
            $stats = $this->isSuperAdmin()
                ? $this->getChatStats($adminId, $this->getCurrentUserRole())
                : (function () use ($adminId) {
                    $mine = $this->serviceRequestModel->getAdminStats($adminId);
                    return ['all' => $mine, 'assigned_to_me' => $mine, 'unassigned' => 0];
                })();

            return $this->response->setJSON([
                'success'  => true,
                'data'     => $rows,
                'has_more' => $hasMore,
                'stats'    => $stats,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getRequestsList error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في تحميل الطلبات: ' . $e->getMessage()]);
        }
    }

    /** تفاصيل طلب */
    public function getRequestDetails($requestId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            if (!$requestId || !is_numeric($requestId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'معرف الطلب غير صحيح']);
            }

            $request = $this->serviceRequestModel->getRequestWithDetails($requestId);
            if (!$request) return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
            if (!$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'ليس لديك صلاحية لعرض هذا الطلب']);
            }

            $messages    = $this->messageModel->getMessagesForRequest($requestId, $this->getCurrentUserId());
            $attachments = $this->fileModel->getFilesForRequest($requestId);

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'request'     => $request,
                    'messages'    => $messages,
                    'attachments' => $attachments,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getRequestDetails error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في تحميل التفاصيل: ' . $e->getMessage()]);
        }
    }

    /** تاريخ رسائل أقدم (limit/before_id/order) */
    public function getMessageHistory($requestId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            $request = $this->serviceRequestModel->find($requestId);
            if (!$request || !$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
            }

            $limit    = (int) ($this->request->getGet('limit') ?? 5);
            $beforeId = (int) ($this->request->getGet('before_id') ?? 0);
            $order    = (string) ($this->request->getGet('order') ?? 'desc');

            $limit = max(1, min(50, $limit));
            $messages = $this->messageModel->getOlderMessages($requestId, $beforeId, $limit, $order);

            return $this->response->setJSON([
                'success'  => true,
                'messages' => $messages,
                'has_more' => count($messages) === $limit,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getMessageHistory error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في تحميل التاريخ: ' . $e->getMessage()]);
        }
    }

    /** إرسال رسالة (نص/ملفات/صوت) */
    public function sendMessage($requestId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            $request = $this->serviceRequestModel->find($requestId);
            if (!$request) return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
            if (!$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'لا تملك صلاحية']);
            }

            $message     = trim((string) ($this->request->getPost('message') ?? ''));
            $messageType = (string) ($this->request->getPost('message_type') ?? 'text');
            $audioSecs   = (int) ($this->request->getPost('audio_duration') ?? 0);

            $attachments = $this->collectAttachments();
            $hasFiles    = !empty($attachments);

            if ($messageType === 'voice') {
                if ($audioSecs <= 0 || $audioSecs > 180) {
                    return $this->response->setJSON(['success' => false, 'message' => 'التسجيل الصوتي يجب ألا يتجاوز 3 دقائق']);
                }
                if (!$hasFiles || !$this->hasAudioFile($attachments)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'يجب إرفاق ملف صوتي صالح']);
                }
            }
            if ($message === '' && !$hasFiles) {
                return $this->response->setJSON(['success' => false, 'message' => 'يجب كتابة رسالة أو إرفاق ملف']);
            }

            $this->db->transStart();

            $msgData = [
                'service_request_id' => (int) $requestId,
                'sender_id'          => (int) $this->getCurrentUserId(),
                'message_type'       => $messageType, // text | file | voice
                'message'            => $message ?: '',
                'is_read'            => 0,
            ];
            $messageId = $this->messageModel->addMessage($msgData);
            if (!$messageId) {
                $this->db->transRollback();
                return $this->response->setJSON(['success' => false, 'message' => 'فشل في إرسال الرسالة']);
            }

            $firstFileId = null;
            if ($hasFiles) {
                foreach (array_values($attachments) as $idx => $file) {
                    $uploaded = $this->uploadFile($file);
                    if (!$uploaded) continue;

                    $fileType = 'attachment';
                    if ($messageType === 'voice') {
                        $name = strtolower((string) $file->getClientName());
                        $cm   = strtolower((string) $file->getClientMimeType());
                        if (strpos($cm, 'audio/') === 0 || preg_match('/\.(webm|m4a|mp3|ogg|wav|aac|flac|opus)$/i', $name)) {
                            $fileType = 'voice';
                        }
                    }
                    $this->fileModel->attachFileToMessage((int) $messageId, (int) $uploaded['file_id'], $fileType, (int) $idx);
                    if ($firstFileId === null) $firstFileId = (int) $uploaded['file_id'];
                }
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                return $this->response->setJSON(['success' => false, 'message' => 'تعذر حفظ الرسالة أو المرفقات']);
            }

            // إحضار الرسالة بصيغتها الموحّدة المستخدمة في الواجهة
            $msg = $this->messageModel->getMessagesForRequest((int) $requestId, (int) $this->getCurrentUserId(), 1, ((int) $messageId) - 1);
            $msg = !empty($msg) ? $msg[0] : $this->messageModel->find((int) $messageId);

            if (is_array($msg)) {
                $msg['id'] = (int) $messageId;
                if ($messageType === 'voice' && $firstFileId) {
                    $msg['download_url'] = site_url('admin/service-requests-chat/file/' . $firstFileId);
                    $msg['file_id']      = $firstFileId;
                }
            }

            return $this->response->setJSON(['success' => true, 'message' => 'تم إرسال الرسالة بنجاح', 'data' => $msg]);
        } catch (\Throwable $e) {
            log_message('error', 'sendMessage error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في إرسال الرسالة: ' . $e->getMessage()]);
        }
    }

    /** عدّادات غير المقروء */
    public function getUnreadCounts()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request'])->setStatusCode(400);
        }
        if (!$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مصرح'])->setStatusCode(403);
        }

        try {
            $viewerId = (int) ($this->getCurrentUserId() ?? session('user_id') ?? 0);
            if ($viewerId <= 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'جلسة غير صالحة'])->setStatusCode(401);
            }

            // optional: request_ids=1,2,3
            $idsParam  = $this->request->getGet('request_ids') ?? $this->request->getGet('ids');
            $filterIds = [];
            if (!empty($idsParam)) {
                foreach (explode(',', $idsParam) as $id) {
                    $id = (int) trim($id);
                    if ($id > 0) $filterIds[] = $id;
                }
            }

            // per-request counts
            $b = $this->db->table('service_request_messages srm')
                ->select('srm.service_request_id, COUNT(*) AS unread_count')
                ->join('service_requests sr', 'sr.id = srm.service_request_id', 'inner')
                ->where('srm.is_deleted', 0)
                ->where('srm.deleted_at IS NULL', null, false)
                ->where('srm.is_read', 0)
                ->where('sr.status !=', 'closed')
                ->where('srm.sender_id !=', $viewerId)
                ->groupBy('srm.service_request_id');

            if (!empty($filterIds)) $b->whereIn('srm.service_request_id', $filterIds);

            $rows = $b->get()->getResultArray();

            $counts = [];
            foreach ($rows as $r) {
                $counts[(int) $r['service_request_id']] = (int) $r['unread_count'];
            }

            // total
            $tb = $this->db->table('service_request_messages srm')
                ->select('COUNT(*) AS c')
                ->join('service_requests sr', 'sr.id = srm.service_request_id', 'inner')
                ->where('srm.is_deleted', 0)
                ->where('srm.deleted_at IS NULL', null, false)
                ->where('srm.is_read', 0)
                ->where('sr.status !=', 'closed')
                ->where('srm.sender_id !=', $viewerId);

            if (!empty($filterIds)) $tb->whereIn('srm.service_request_id', $filterIds);

            $total = (int) ($tb->get()->getRow('c') ?? 0);

            return $this->response->setJSON([
                'success'      => true,
                'counts'       => $counts,
                'total_unread' => $total,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getUnreadCounts error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ غير متوقع'])->setStatusCode(500);
        }
    }

    /** حذف رسالة */
    public function deleteMessage($messageId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            $message = $this->messageModel->find((int) $messageId);
            if (!$message) {
                return $this->response->setJSON(['success' => false, 'message' => 'الرسالة غير موجودة']);
            }
            if ((int) $message['sender_id'] !== (int) $this->getCurrentUserId()) {
                return $this->response->setJSON(['success' => false, 'message' => 'لا يمكنك حذف رسائل المستخدمين الآخرين']);
            }
            $ok = $this->messageModel->deleteMessage((int) $messageId);

            return $this->response->setJSON([
                'success' => (bool) $ok,
                'message' => $ok ? 'تم حذف الرسالة بنجاح' : 'فشل في حذف الرسالة'
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'deleteMessage error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في حذف الرسالة']);
        }
    }

    /** رسائل جديدة (last_message_id | last_id) */
    public function getNewMessages($requestId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            $lastParam     = $this->request->getGet('last_message_id');
            $lastFallback  = $this->request->getGet('last_id');
            $lastMessageId = ($lastParam !== null) ? (int) $lastParam : (int) ($lastFallback ?? 0);

            $currentUserId = $this->getCurrentUserId();

            $request = $this->serviceRequestModel->find($requestId);
            if (!$request || !$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
            }

            $newMessages = $this->messageModel->getNewMessages($requestId, $lastMessageId, $currentUserId);

            return $this->response->setJSON(['success' => true, 'data' => $newMessages]);
        } catch (\Throwable $e) {
            log_message('error', 'getNewMessages error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'data' => []]);
        }
    }

    /** تعليم كمقروء */
    public function markAsRead($requestId)
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }
        try {
            $request = $this->serviceRequestModel->find($requestId);
            if (!$request || !$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
            }

            $this->messageModel->markAsRead($requestId, $this->getCurrentUserId());

            return $this->response->setJSON(['success' => true, 'message' => 'تم تعليم الرسائل كمقروءة']);
        } catch (\Throwable $e) {
            log_message('error', 'markAsRead error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => true, 'message' => 'تم تعليم الرسائل كمقروءة']);
        }
    }

    /** تحديث حالة الطلب */
    public function updateStatus($id = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false, 'message' => 'AJAX only'
            ]);
        }

        $id     = $id ?? (int) $this->request->getPost('service_request_id');
        $status = trim((string) $this->request->getPost('status'));

        if (!$id) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false, 'message' => 'service_request_id مطلوب'
            ]);
        }

        $allowed = ['pending','in_progress','resolved','closed','cancelled'];
        if (!in_array($status, $allowed, true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false, 'message' => 'قيمة الحالة غير صالحة'
            ]);
        }

        $model = new \App\Models\ServiceRequestModel();
        $req   = $model->find($id);
        if (!$req) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false, 'message' => 'الطلب غير موجود'
            ]);
        }

        if (!$model->update($id, ['status' => $status])) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false, 'message' => 'فشل تحديث الحالة'
            ]);
        }

        $updated = $model->find($id);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم تحديث الحالة',
            'data'    => ['request' => $updated],
        ]);
    }

    /** تحميل/عرض ملف */
    public function downloadFile($fileId)
    {
        if (!$this->isAdmin()) {
            return $this->response->setStatusCode(403)->setJSON(['message' => 'غير مسموح']);
        }

        try {
            $userId = (int) $this->getCurrentUserId();
            $file   = $this->fileModel->getFileWithPermissionsForAdmin((int) $fileId, $userId);

            // سوبر أدمن: تخطي الصلاحيات إن لزم
            if (!$file && $this->isSuperAdmin()) {
                $file = $this->filesModel->find((int) $fileId);
                if (!$file) return $this->response->setStatusCode(404)->setJSON(['message' => 'الملف غير موجود']);
            }

            if (!$file) {
                return $this->response->setStatusCode(404)->setJSON(['message' => 'الملف غير موجود']);
            }

            $storedPath = (string) ($file['file_path'] ?? '');
            $absPath    = null;

            if ($storedPath && is_file($storedPath)) {
                $absPath = $storedPath;
            } else {
                $rel = ltrim($storedPath, "/\\");
                foreach ([WRITEPATH . $rel, FCPATH . $rel, ROOTPATH . $rel] as $p) {
                    if (is_file($p)) { $absPath = $p; break; }
                }
            }

            if (!$absPath || !is_file($absPath)) {
                return $this->response->setStatusCode(404)->setJSON(['message' => 'الملف غير موجود على الخادم']);
            }

            $inline   = in_array((string) $this->request->getGet('inline'), ['1','true','yes'], true);
            $fileName = $file['file_name'] ?? basename($absPath);
            $mime     = $file['mime_type'] ?? 'application/octet-stream';

            $resp = $this->response
                ->download($absPath, null)
                ->setFileName($fileName)
                ->setHeader('Content-Type', $mime);

            $resp->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $fileName . '"');
            if (strpos($mime, 'audio/') === 0 || strpos($mime, 'video/') === 0) {
                $resp->setHeader('Accept-Ranges', 'bytes');
            }
            return $resp;
        } catch (\Throwable $e) {
            log_message('error', 'downloadFile error: ' . $e->getMessage() . ' | File ID: ' . (int) $fileId);
            return $this->response->setStatusCode(500)->setJSON(['message' => 'حدث خطأ في تحميل الملف']);
        }
    }

    /* =================== Helpers =================== */

    private function getChatStats($adminId, $adminRole)
    {
        $allStats   = $this->serviceRequestModel->getAdminStats();
        $myStats    = $this->serviceRequestModel->getAdminStats($adminId);
        $unassigned = $this->serviceRequestModel->countForAdmin(null, null, true);

        return [
            'all'            => $allStats,
            'assigned_to_me' => $myStats,
            'unassigned'     => $unassigned,
        ];
    }

    /** استعلام واحد للعدّادات لكل قائمة IDs */
    private function getUnreadCountsForRequests(array $requestIds, int $viewerId): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
        if (empty($requestIds)) return [];

        $rows = $this->db->table('service_request_messages srm')
            ->select('srm.service_request_id, COUNT(*) AS unread_count')
            ->join('service_requests sr', 'sr.id = srm.service_request_id', 'inner')
            ->whereIn('srm.service_request_id', $requestIds)
            ->where('srm.is_deleted', 0)
            ->where('srm.deleted_at IS NULL', null, false)
            ->where('srm.is_read', 0)
            ->where('sr.status !=', 'closed')
            ->where('srm.sender_id !=', $viewerId)
            ->groupBy('srm.service_request_id')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['service_request_id']] = (int) $r['unread_count'];
        }
        return $map;
    }

    /** رسالة نظامية (لو احتجتها لاحقًا) */
    private function addSystemMessage($requestId, $message)
    {
        try {
            $this->messageModel->addMessage([
                'service_request_id' => (int) $requestId,
                'sender_id'          => (int) $this->getCurrentUserId(),
                'message_type'       => 'text',
                'message'            => '[SYSTEM] ' . $message,
                'is_read'            => 0,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'addSystemMessage error: ' . $e->getMessage());
        }
    }

    private function collectAttachments(): array
    {
        $files = $this->request->getFiles();
        $out   = [];

        if (isset($files['files']) && is_array($files['files'])) {
            foreach ($files['files'] as $f) {
                if ($f instanceof UploadedFile && $f->isValid() && !$f->hasMoved()) $out[] = $f;
            }
        }
        if (isset($files['attachments']) && is_array($files['attachments'])) {
            foreach ($files['attachments'] as $f) {
                if ($f instanceof UploadedFile && $f->isValid() && !$f->hasMoved()) $out[] = $f;
            }
        }
        if (isset($files['voice']) && $files['voice'] instanceof UploadedFile) {
            $f = $files['voice'];
            if ($f->isValid() && !$f->hasMoved()) $out[] = $f;
        }
        return $out;
    }

    private function hasAudioFile(array $files): bool
    {
        foreach ($files as $file) {
            $mime = strtolower((string) $file->getClientMimeType());
            $name = strtolower((string) $file->getClientName());
            if (strpos($mime, 'audio/') === 0) return true;
            if (preg_match('/\.(webm|m4a|mp3|ogg|wav|aac|flac|opus)$/i', $name)) return true;
        }
        return false;
    }

    private function uploadFile(\CodeIgniter\HTTP\Files\UploadedFile $file): ?array
    {
        if (!$file->isValid() || $file->hasMoved()) return null;

        $clientName = $file->getClientName();
        $clientMime = $file->getClientMimeType();
        $sizeBytes  = $file->getSize();

        $targetDir = WRITEPATH . 'uploads/' . date('Y/m/d');
        if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);

        $newName = $file->getRandomName();
        if (!$file->move($targetDir, $newName, true)) return null;

        $absPath   = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
        $mime      = $clientMime ?: (is_file($absPath) ? mime_content_type($absPath) : 'application/octet-stream');
        $sizeFinal = is_file($absPath) ? filesize($absPath) : $sizeBytes;

        $data = [
            'file_name'       => $clientName,
            'file_path'       => $absPath,
            'mime_type'       => $mime ?: 'application/octet-stream',
            'file_size_bytes' => (int) $sizeFinal,
            'uploader_id'     => (int) $this->getCurrentUserId(),
            'uploaded_at'     => date('Y-m-d H:i:s'),
            'description'     => null,
            'is_premium'      => 0,
        ];

        $fileId = $this->filesModel->insert($data, true);
        if (!$fileId) return null;

        return ['file_id' => (int) $fileId, 'path' => $absPath];
    }

    private function canViewRequest($request): bool
    {
        if ($this->isSuperAdmin()) return true;
        return (int) ($request['assigned_to'] ?? 0) === (int) $this->getCurrentUserId();
    }

    private function canModifyRequest($request): bool
    {
        return $this->canViewRequest($request);
    }

    private function getStatusText($status): string
    {
        $map = [
            'pending'     => 'في الانتظار',
            'in_progress' => 'قيد المعالجة',
            'resolved'    => 'تم الحل',
            'closed'      => 'مغلق',
            'cancelled'   => 'ملغي'
        ];
        return $map[$status] ?? $status;
    }

    private function isAdmin(): bool
    {
        $userId = session('user_id');
        $roleId = session('role');
        if (!$userId || !$roleId) return false;
        return in_array((int) $roleId, [1, 2, 4], true);
    }

    private function isSuperAdmin(): bool
    {
        return $this->getCurrentUserRole() === 1;
    }

    private function getCurrentUserId()
    {
        return (int) session('user_id');
    }

    private function getCurrentUserRole(): int
    {
        return (int) session('role');
    }
}
