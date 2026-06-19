<?php

namespace App\Controllers;

use App\Models\ServiceRequestModel;
use App\Models\ServiceRequestMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\FilesModel;
use App\Models\UserModel;
use App\Libraries\RemoteUsersClient;
use CodeIgniter\HTTP\Files\UploadedFile;

class AdminServiceRequestsNewController extends BaseController
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

    /** @var RemoteUsersClient */
    private $remoteUsers;

    public function __construct()
    {
        $this->db                  = \Config\Database::connect();
        $this->serviceRequestModel = new ServiceRequestModel();
        $this->messageModel        = new ServiceRequestMessageModel();
        $this->fileModel           = new ServiceRequestFileModel();
        $this->filesModel          = new FilesModel();
        $this->userModel           = new UserModel();
        $this->remoteUsers         = new RemoteUsersClient();
    }

    /** صفحة الواجهة الجديدة */
    public function index()
    {
        if (!$this->isAdmin()) return redirect()->to('/login');

        $data = [
            'title'          => 'طلبات الخدمة - جديد',
            'active_section' => 'service_requests_new',
        ];
        // محاولة استخدام view من admins أولاً، ثم admin كبديل
        $viewPath = 'admins/pages/service_requests_new';
        if (!file_exists(APPPATH . 'Views/' . str_replace('/', DIRECTORY_SEPARATOR, $viewPath) . '.php')) {
            $viewPath = 'admin/pages/service_requests_new';
        }
        return view($viewPath, $data);
    }

    /** قائمة الطلبات مع الفلاتر */
    public function getRequestsList()
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        try {
            $adminId   = (int) $this->getCurrentUserId();
            $assigned  = (string) $this->request->getGet('assigned'); // all | me | unassigned
            $status    = (string) $this->request->getGet('status');
            $limit     = (int) ($this->request->getGet('limit') ?? 20);
            $beforeId  = (int) ($this->request->getGet('before_id') ?? 0);

            $limit = max(1, min(100, $limit));

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
                    du.phone_number AS requester_phone,
                    un.name AS requester_university,
                    sp.name AS requester_specialty,
                    ay.label AS requester_academic_year
                ")
                ->join('diploma_users du', 'du.user_id = sr.user_id', 'left')
                ->join('universities un', 'un.id = du.university_id', 'left')
                ->join('specialties sp', 'sp.id = du.specialty_id', 'left')
                ->join('academic_years ay', 'ay.id = du.academic_year_id', 'left');

            $allowedStatuses = ['pending','in_progress','resolved','closed','cancelled'];
            if ($status && in_array($status, $allowedStatuses, true)) {
                $b->where('sr.status', $status);
            }

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

            $rows = $b->orderBy('sr.id', 'DESC')
                      ->limit($limit + 1)
                      ->get()
                      ->getResultArray();

            $hasMore = false;
            if (count($rows) > $limit) {
                $hasMore = true;
                $rows = array_slice($rows, 0, $limit);
            }

            if (empty($rows)) {
                $stats = $this->isSuperAdmin()
                    ? $this->getChatStats($adminId, $this->getCurrentUserRole())
                    : (function () use ($adminId) {
                        $mine = $this->serviceRequestModel->getStats(null, $adminId);
                        return ['all' => $mine, 'assigned_to_me' => $mine, 'unassigned' => 0];
                    })();

                return $this->response->setJSON(['success' => true, 'data' => [], 'has_more' => false, 'stats' => $stats]);
            }

            $requestIds   = array_map('intval', array_column($rows, 'id'));
            $unreadCounts = $this->getUnreadCountsForRequests($requestIds, $adminId);

            $idsList = implode(',', $requestIds);
            $sqlLast = "
                SELECT m.service_request_id, m.id, m.message, m.message_type, m.created_at, m.sender_id
                FROM service_request_messages m
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

            $makeWordsPreview = static function (?string $text, int $maxWords = 10): string {
                $text = trim((string) strip_tags($text ?? ''));
                if ($text === '') return '';
                $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($words) > $maxWords) {
                    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
                }
                return $text;
            };

            // جلب الأسماء من RemoteUsersClient
            $userIds = [];
            foreach ($rows as $r) {
                if (!empty($r['user_id'])) $userIds[] = (int) $r['user_id'];
                if (!empty($r['assigned_to'])) $userIds[] = (int) $r['assigned_to'];
            }
            foreach ($lastMap as $lm) {
                if (!empty($lm['sender_id'])) $userIds[] = (int) $lm['sender_id'];
            }
            $userIds = array_values(array_unique(array_filter($userIds)));
            $namesMap = !empty($userIds) ? $this->remoteUsers->resolveMany($userIds) : [];

            foreach ($rows as &$r) {
                $rid = (int) $r['id'];
                $r['unread_count'] = $unreadCounts[$rid] ?? 0;

                // جلب اسم الطالب من RemoteUsersClient
                $requesterId = (int) ($r['user_id'] ?? 0);
                $requesterData = $namesMap[$requesterId] ?? ['name' => null, 'email' => null];
                $r['requester_name'] = $requesterData['name'] ?? 'مستخدم #' . $requesterId;
                $r['requester_email'] = $requesterData['email'] ?? '';

                // جلب اسم الآدمن المكلّف من RemoteUsersClient
                $assignedToId = (int) ($r['assigned_to'] ?? 0);
                if ($assignedToId > 0) {
                    $adminData = $namesMap[$assignedToId] ?? ['name' => null, 'email' => null];
                    $r['assigned_admin_name'] = $adminData['name'] ?? 'مستخدم #' . $assignedToId;
                } else {
                    $r['assigned_admin_name'] = null;
                }

                $lm = $lastMap[$rid] ?? null;
                if ($lm) {
                    $type       = strtolower((string) ($lm['message_type'] ?? 'text'));
                    $senderId   = (int) ($lm['sender_id'] ?? 0);
                    $senderData = $namesMap[$senderId] ?? ['name' => null, 'email' => null];
                    $senderName = ($senderId === (int) $adminId)
                        ? 'أنت'
                        : ($senderData['name'] ?? 'مستخدم');
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

            $stats = $this->isSuperAdmin()
                ? $this->getChatStats($adminId, $this->getCurrentUserRole())
                : (function () use ($adminId) {
                    $mine = $this->serviceRequestModel->getStats(null, $adminId);
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

            // جلب تفاصيل الطلب مع بيانات المستخدم من diploma_users (الجامعة، التخصص، السنة الأكاديمية)
            $request = $this->db->table('service_requests sr')
                ->select('
                    sr.*,
                    du.phone_number AS requester_phone,
                    un.name AS requester_university,
                    sp.name AS requester_specialty,
                    ay.label AS requester_academic_year
                ')
                ->join('diploma_users du', 'du.user_id = sr.user_id', 'left')
                ->join('universities un', 'un.id = du.university_id', 'left')
                ->join('specialties sp', 'sp.id = du.specialty_id', 'left')
                ->join('academic_years ay', 'ay.id = du.academic_year_id', 'left')
                ->where('sr.id', $requestId)
                ->get()
                ->getRowArray();
                
            if (!$request) return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
            if (!$this->canViewRequest($request)) {
                return $this->response->setJSON(['success' => false, 'message' => 'ليس لديك صلاحية لعرض هذا الطلب']);
            }

            // جلب الأسماء من RemoteUsersClient
            $userIds = [];
            if (!empty($request['user_id'])) $userIds[] = (int) $request['user_id'];
            if (!empty($request['assigned_to'])) $userIds[] = (int) $request['assigned_to'];
            $namesMap = !empty($userIds) ? $this->remoteUsers->resolveMany($userIds) : [];
            
            $requesterId = (int) ($request['user_id'] ?? 0);
            $requesterData = $namesMap[$requesterId] ?? ['name' => null, 'email' => null];
            $request['requester_name'] = $requesterData['name'] ?? 'مستخدم #' . $requesterId;
            $request['requester_email'] = $requesterData['email'] ?? '';
            
            $assignedToId = (int) ($request['assigned_to'] ?? 0);
            if ($assignedToId > 0) {
                $adminData = $namesMap[$assignedToId] ?? ['name' => null, 'email' => null];
                $request['assigned_admin_name'] = $adminData['name'] ?? 'مستخدم #' . $assignedToId;
                $request['assigned_admin_email'] = $adminData['email'] ?? '';
            } else {
                $request['assigned_admin_name'] = null;
                $request['assigned_admin_email'] = null;
            }

            // جلب الرسائل
            $messages = $this->messageModel->getMessagesForRequest($requestId, $this->getCurrentUserId());
            
            // تسجيل للتشخيص
            log_message('debug', "getRequestDetails: Found " . count($messages) . " messages for request {$requestId}");
            
            // التأكد من أن الرسائل مصفوفة
            if (!is_array($messages)) {
                log_message('error', "getRequestDetails: messages is not an array, got: " . gettype($messages));
                $messages = [];
            }
            
            // جلب أسماء المرسلين من RemoteUsersClient
            $senderIds = [];
            if (!empty($messages) && is_array($messages)) {
                foreach ($messages as $msg) {
                    if (isset($msg['sender_id']) && !empty($msg['sender_id'])) {
                        $senderIds[] = (int) $msg['sender_id'];
                    }
                }
                $senderIds = array_values(array_unique(array_filter($senderIds)));
            }
            
            $senderNamesMap = !empty($senderIds) ? $this->remoteUsers->resolveMany($senderIds) : [];
            $currentUserId = $this->getCurrentUserId();
            
            foreach ($messages as &$msg) {
                if (!is_array($msg)) continue;
                
                $senderId = (int) ($msg['sender_id'] ?? 0);
                if ($senderId === $currentUserId) {
                    $msg['sender_name'] = 'أنت';
                } else {
                    $senderData = $senderNamesMap[$senderId] ?? ['name' => null, 'email' => null];
                    $msg['sender_name'] = $senderData['name'] ?? 'مستخدم';
                }
                
                // التأكد من وجود الحقول الأساسية
                if (!isset($msg['id'])) {
                    log_message('error', "getRequestDetails: Message missing id field: " . json_encode($msg));
                }
                if (!isset($msg['message_type'])) {
                    $msg['message_type'] = 'text';
                }
                if (!isset($msg['message'])) {
                    $msg['message'] = '';
                }
            }
            unset($msg);
            
            $attachments = $this->fileModel->getFilesForRequest($requestId);

            // تسجيل للتشخيص
            log_message('debug', "getRequestDetails: Returning " . count($messages) . " messages");
            
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

    /** رسائل جديدة */
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
            
            // جلب أسماء المرسلين من RemoteUsersClient
            $senderIds = array_values(array_unique(array_filter(array_column($newMessages, 'sender_id'))));
            $senderNamesMap = !empty($senderIds) ? $this->remoteUsers->resolveMany($senderIds) : [];
            
            foreach ($newMessages as &$msg) {
                $senderId = (int) ($msg['sender_id'] ?? 0);
                if ($senderId === $currentUserId) {
                    $msg['sender_name'] = 'أنت';
                } else {
                    $senderData = $senderNamesMap[$senderId] ?? ['name' => null, 'email' => null];
                    $msg['sender_name'] = $senderData['name'] ?? 'مستخدم';
                }
            }
            unset($msg);

            return $this->response->setJSON(['success' => true, 'data' => $newMessages]);
        } catch (\Throwable $e) {
            log_message('error', 'getNewMessages error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'data' => []]);
        }
    }

    /** إرسال رسالة */
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
                'message_type'       => $messageType,
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

            $msg = $this->messageModel->getMessagesForRequest((int) $requestId, (int) $this->getCurrentUserId(), 1, ((int) $messageId) - 1);
            $msg = !empty($msg) ? $msg[0] : $this->messageModel->find((int) $messageId);

            if (is_array($msg)) {
                $msg['id'] = (int) $messageId;
                if ($messageType === 'voice' && $firstFileId) {
                    $msg['download_url'] = site_url('admin/service-requests-new/file/' . $firstFileId);
                    $msg['file_id']      = $firstFileId;
                }
            }

            return $this->response->setJSON(['success' => true, 'message' => 'تم إرسال الرسالة بنجاح', 'data' => $msg]);
        } catch (\Throwable $e) {
            log_message('error', 'sendMessage error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في إرسال الرسالة: ' . $e->getMessage()]);
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

        $req   = $this->serviceRequestModel->find($id);
        if (!$req) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false, 'message' => 'الطلب غير موجود'
            ]);
        }

        if (!$this->serviceRequestModel->update($id, ['status' => $status])) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false, 'message' => 'فشل تحديث الحالة'
            ]);
        }

        $updated = $this->serviceRequestModel->find($id);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم تحديث الحالة',
            'data'    => ['request' => $updated],
        ]);
    }

    /** تكليف طلب */
    public function assignRequest($requestId = null)
    {
        if (!$this->request->isAJAX() || !$this->isAdminRole()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        $data      = $this->input();
        $requestId = $requestId ?: (int) ($data['request_id'] ?? 0);
        $adminId   = (int) ($data['admin_id'] ?? 0);

        if ($requestId <= 0 || $adminId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'بيانات ناقصة']);
        }

        $request = $this->serviceRequestModel->find($requestId);
        $admin   = $this->userModel->find($adminId);

        if (!$request || !$admin) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب أو المستخدم غير موجود']);
        }

        if (!in_array((int) ($admin['account_type_id'] ?? $admin['role'] ?? 0), [1, 2], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'المستخدم المحدد ليس Admin/Teacher']);
        }

        $ok = $this->serviceRequestModel->assignToAdmin($requestId, $adminId);

        if ($ok) {
            $this->addSystemMessage($requestId, 'تم تكليف الطلب للمستخدم: ' . ($admin['name'] ?? ('#' . $adminId)));
            return $this->response->setJSON(['success' => true, 'message' => 'تم تكليف الطلب بنجاح']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'فشل في التكليف']);
    }

    /** قائمة الأدمن للتكليف */
    public function getAdminsList()
    {
        if (!$this->request->isAJAX() || !$this->isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'غير مسموح']);
        }

        try {
            // جلب IDs من diploma_users
            $adminRows = $this->db->table('diploma_users')
                ->select('user_id, account_type_id')
                ->whereIn('account_type_id', [1, 2])
                ->where('user_id IS NOT NULL', null, false)
                ->where('user_id !=', 0)
                ->get()
                ->getResultArray();
            
            $adminIds = array_values(array_unique(array_filter(array_column($adminRows, 'user_id'))));
            
            if (empty($adminIds)) {
                return $this->response->setJSON(['success' => true, 'data' => []]);
            }
            
            // جلب الأسماء من RemoteUsersClient
            $namesMap = $this->remoteUsers->resolveMany($adminIds);
            
            // بناء القائمة
            $admins = [];
            foreach ($adminIds as $adminId) {
                $adminData = $namesMap[$adminId] ?? ['name' => null, 'email' => null];
                $admins[] = [
                    'id' => (int) $adminId,
                    'name' => $adminData['name'] ?? 'مستخدم #' . $adminId,
                    'email' => $adminData['email'] ?? '',
                ];
            }
            
            // ترتيب حسب الاسم
            usort($admins, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            return $this->response->setJSON(['success' => true, 'data' => $admins]);
        } catch (\Throwable $e) {
            log_message('error', 'getAdminsList error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'حدث خطأ في تحميل القائمة']);
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

    /** تحميل ملف */
    public function downloadFile($fileId)
    {
        if (!$this->isAdmin()) {
            return $this->response->setStatusCode(403)->setJSON(['message' => 'غير مسموح']);
        }

        try {
            $userId = (int) $this->getCurrentUserId();
            $file   = $this->fileModel->getFileWithPermissionsForAdmin((int) $fileId, $userId);

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
        // استخدام getStats بدلاً من getAdminStats
        $allStats   = $this->serviceRequestModel->getStats(); // جميع الطلبات
        $myStats    = $this->serviceRequestModel->getStats(null, $adminId); // طلبات المكلّفة للمستخدم الحالي
        $unassigned = $this->serviceRequestModel->countForAdmin(null, null, true, null); // غير مكلفة

        return [
            'all'            => $allStats,
            'assigned_to_me' => $myStats,
            'unassigned'     => $unassigned,
        ];
    }

    private function getUnreadCountsForRequests(array $requestIds, int $viewerId): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
        if (empty($requestIds)) return [];

        $builder = $this->db->table('service_request_messages srm')
            ->select('srm.service_request_id, COUNT(*) AS unread_count')
            ->join('service_requests sr', 'sr.id = srm.service_request_id', 'inner')
            ->whereIn('srm.service_request_id', $requestIds)
            ->where('srm.is_read', 0)
            ->where('sr.status !=', 'closed')
            ->where('srm.sender_id !=', $viewerId);

        $builder->groupStart()
            ->where('srm.is_deleted', 0)
            ->orWhere('srm.is_deleted IS NULL', null, false)
        ->groupEnd();

        $builder->groupStart()
            ->where('srm.deleted_at IS NULL', null, false)
            ->orWhere('srm.deleted_at', '')
        ->groupEnd();

        $rows = $builder
            ->groupBy('srm.service_request_id')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['service_request_id']] = (int) $r['unread_count'];
        }
        return $map;
    }

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

    private function isAdminRole(): bool
    {
        return $this->isSuperAdmin();
    }

    private function getCurrentUserId()
    {
        return (int) session('user_id');
    }

    private function getCurrentUserRole(): int
    {
        return (int) session('role');
    }

    private function input(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && !empty($json)) {
            return $json;
        }
        return $this->request->getPost() ?? [];
    }
}

