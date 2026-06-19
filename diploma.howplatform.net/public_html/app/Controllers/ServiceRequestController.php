<?php

// app/Controllers/ServiceRequestController.php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ServiceRequestModel;
use App\Models\ServiceRequestMessageModel;
use App\Models\ServiceRequestFileModel;
use App\Models\FileModel;
use App\Models\CourseOfferingModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class ServiceRequestController extends BaseController
{
    // ===================== Models =====================
    protected ServiceRequestModel $serviceRequestModel;
    protected ServiceRequestMessageModel $messageModel;
    protected FileModel $fileModel;
    protected ServiceRequestFileModel $requestFileModel;
    protected CourseOfferingModel $courseOfferingModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->serviceRequestModel  = new ServiceRequestModel();
        $this->messageModel         = new ServiceRequestMessageModel();
        $this->fileModel            = new FileModel();
        $this->requestFileModel     = new ServiceRequestFileModel();
        $this->courseOfferingModel  = new CourseOfferingModel();
        $this->userModel            = new UserModel();
    }

    /**
     * ✅ التحقق من الجلسة والصلاحيات
     * - للطالب (role=3): يسمح فقط بطلباته
     * - للأدمن (role=1,2): وصول عام
     */
    private function checkUserAccess(?int $requestId = null): array
    {
        // لوج تشخيصي للجلسة
        $allSession = session()->get();
        log_message('info', 'Full session data: ' . json_encode($allSession));

        $userId   = (int) session()->get('user_id');
        $userRole = (int) session()->get('role');

        log_message('info', "User access check - UserId: {$userId}, Role: {$userRole}, RequestId: " . ($requestId ?? 'null'));

        if (!$userId) {
            log_message('error', 'No user_id in session. Session ID: ' . session_id());
            return [
                'success'     => false,
                'message'     => 'يجب تسجيل الدخول أولاً',
                'status_code' => 401,
            ];
        }

        if ($requestId) {
            $request = $this->serviceRequestModel
                ->select('id, user_id, status')
                ->where('id', $requestId)
                ->first();

            if (!$request) {
                log_message('error', "Request {$requestId} not found in database");
                return [
                    'success'     => false,
                    'message'     => 'الطلب غير موجود',
                    'status_code' => 404,
                ];
            }

            log_message('info', "Request {$requestId} found - owner: {$request['user_id']}, status: {$request['status']}");

            if ($userRole === 3) {
                if ((int) $request['user_id'] !== $userId) {
                    log_message('error', "Access denied - User {$userId} (role: {$userRole}) trying to access request {$requestId} owned by {$request['user_id']}");
                    return [
                        'success'     => false,
                        'message'     => 'غير مصرح لك بالوصول لهذا الطلب',
                        'status_code' => 403,
                    ];
                }
            } elseif (!in_array($userRole, [1, 2], true)) {
                log_message('error', "Invalid user role: {$userRole} for user {$userId}");
                return [
                    'success'     => false,
                    'message'     => 'دور المستخدم غير صالح',
                    'status_code' => 403,
                ];
            }
        }

        log_message('info', "Access granted for user {$userId} (role: {$userRole}) to request " . ($requestId ?? 'general'));

        return [
            'success'   => true,
            'user_id'   => $userId,
            'user_role' => $userRole,
        ];
    }

    /**
     * ✅ دالة فحص الجلسة (تشخيص)
     */
    public function checkSession()
    {
        $userId   = session()->get('user_id');
        $userRole = session()->get('role');
        $userName = session()->get('user_name');

        log_message('info', "Session check - UserId: {$userId}, Role: {$userRole}, Name: {$userName}");

        return $this->response->setJSON([
            'success'      => true,
            'session_data' => [
                'user_id'         => $userId,
                'role' => $userRole,
                'user_name'       => $userName,
                'session_id'      => session_id(),
                'is_logged_in'    => !empty($userId),
            ],
        ]);
    }

    // ---------------------------------
    // صفحة الواجهة (الطالب)
    // ---------------------------------
    public function index()
    {
        return view('student/pages/service');
    }

    // ---------------------------------
    // قائمة الطلبات + عدّاد غير المقروء
    // ---------------------------------
    public function list()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = $accessCheck['user_id'];
        $userRole = $accessCheck['user_role'];

        try {
            if ($userRole === 3) {
                // للطلاب: طلباتهم فقط
                $requests = $this->serviceRequestModel
                    ->select('service_requests.id, service_requests.subject, service_requests.status, service_requests.created_at, courses.name AS course_name, courses.code AS course_code')
                    ->join('course_offerings', 'course_offerings.id = service_requests.course_offering_id', 'left')
                    ->join('courses', 'courses.id = course_offerings.course_id', 'left')
                    ->where('service_requests.user_id', $userId)
                    ->orderBy('service_requests.created_at', 'DESC')
                    ->findAll();
            } else {
                // للأدمن: كل الطلبات
                $requests = $this->serviceRequestModel
                    ->select('service_requests.id, service_requests.subject, service_requests.status, service_requests.created_at, courses.name AS course_name, courses.code AS course_code, users.name AS user_name')
                    ->join('course_offerings', 'course_offerings.id = service_requests.course_offering_id', 'left')
                    ->join('courses', 'courses.id = course_offerings.course_id', 'left')
                    ->join('users', 'users.id = service_requests.user_id', 'left')
                    ->orderBy('service_requests.created_at', 'DESC')
                    ->findAll();
            }

            if (!$requests) {
                return $this->response->setJSON([
                    'success'  => true,
                    'requests' => [],
                    'counts'   => [
                        'all'         => 0,
                        'pending'     => 0,
                        'in_progress' => 0,
                        'resolved'    => 0,
                        'cancelled'   => 0,
                    ],
                ]);
            }

            $ids = array_column($requests, 'id');

            // عداد غير المقروء دفعة واحدة
            $unreadBuilder = $this->messageModel
                ->select('service_request_id, COUNT(*) AS unread_count')
                ->whereIn('service_request_id', $ids)
                ->where('is_read', 0)
                ->where('deleted_at', null);

            if ($userRole === 3) {
                $unreadBuilder->where('sender_id !=', $userId);
            }

            $unreadRows = $unreadBuilder->groupBy('service_request_id')->findAll();

            $unreadMap = [];
            foreach ($unreadRows as $row) {
                $unreadMap[(int) $row['service_request_id']] = (int) $row['unread_count'];
            }

            foreach ($requests as &$r) {
                $rid               = (int) $r['id'];
                $r['unread_count'] = $unreadMap[$rid] ?? 0;
            }
            unset($r);

            // عدّادات الحالة
            $statusCounts = [
                'pending'     => 0,
                'in_progress' => 0,
                'resolved'    => 0,
                'cancelled'   => 0,
            ];
            foreach ($requests as $r) {
                $st = $r['status'];
                if (isset($statusCounts[$st])) {
                    $statusCounts[$st]++;
                }
            }

            $counts = [
                'all'         => count($requests),
                'pending'     => $statusCounts['pending'],
                'in_progress' => $statusCounts['in_progress'],
                'resolved'    => $statusCounts['resolved'],
                'cancelled'   => $statusCounts['cancelled'],
            ];

            return $this->response->setJSON([
                'success'  => true,
                'requests' => $requests,
                'counts'   => $counts,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error in list(): ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'حدث خطأ في تحميل الطلبات',
            ])->setStatusCode(500);
        }
    }

    // ---------------------------------
    // إنشاء طلب جديد + مرفقات أولية
    // ---------------------------------
    public function create()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId = $accessCheck['user_id'];

        $rules = [
            'subject'            => 'required|min_length[3]|max_length[255]',
            'description'        => 'required|min_length[10]',
            'course_offering_id' => 'permit_empty|numeric',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'البيانات المدخلة غير صحيحة',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $db = \Config\Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            $requestId = (int) $this->serviceRequestModel->insert([
                'user_id'            => $userId,
                'subject'            => (string) $this->request->getPost('subject'),
                'description'        => (string) $this->request->getPost('description'),
                'course_offering_id' => $this->request->getPost('course_offering_id') ?: null,
                'status'             => 'pending',
                'priority'           => 'medium',
            ]);

            // مرفقات أولية (اختياري)
            $files = $this->request->getFiles();
            if (!empty($files['files']) && is_array($files['files'])) {
                $destDir = ROOTPATH . 'public/uploads/services';
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }

                foreach ($files['files'] as $file) {
                    if ($file->isValid() && !$file->hasMoved()) {
                        $newName = $file->getRandomName();
                        $file->move($destDir, $newName);

                        $fileId = (int) $this->fileModel->insert([
                            'file_name'       => $file->getClientName(),
                            'file_path'       => 'uploads/services/' . $newName,
                            'mime_type'       => $file->getClientMimeType(),
                            'file_size_bytes' => $file->getSize(),
                            'uploader_id'     => $userId,
                            'description'     => 'مرفق طلب خدمة',
                        ]);

                        $this->requestFileModel->insert([
                            'service_request_id' => $requestId,
                            'file_id'            => $fileId,
                            'file_type'          => 'attachment',
                        ]);
                    }
                }
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('فشل في حفظ الطلب');
            }

            return $this->response->setJSON([
                'success'    => true,
                'message'    => 'تم إرسال الطلب بنجاح',
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------------
    // ✅ تفاصيل طلب + رسائل مع التحميل الجزئي المُحسّن
    // ---------------------------------
    public function messages(int $id)
    {
        $accessCheck = $this->checkUserAccess($id);
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = (int) $accessCheck['user_id'];
        $limit    = max(1, min(50, (int) ($this->request->getGet('limit') ?? 20)));
        $beforeId = (int) ($this->request->getGet('before_id') ?? 0);
        $order    = strtolower((string) ($this->request->getGet('order') ?? 'desc'));
        $order    = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        log_message('info', "Loading messages for request {$id} - limit: {$limit}, before_id: {$beforeId}, order: {$order}");

        $payload = [
            'success'     => true,
            'request'     => null,
            'attachments' => [],
            'messages'    => [],
            'has_more'    => false,
            'oldest_id'   => null,
            'newest_id'   => null,
        ];

        try {
            // ====================== التحميل الأولي ======================
            if ($beforeId <= 0) {
                // تفاصيل الطلب
                $request = $this->serviceRequestModel
                    ->select(
                        'service_requests.id, service_requests.subject, service_requests.description, ' .
                        'service_requests.status, service_requests.created_at, service_requests.course_offering_id, ' .
                        'courses.name AS course_name, courses.code AS course_code, ' .
                        'users.name AS requester_name, users.email AS requester_email'
                    )
                    ->join('course_offerings', 'course_offerings.id = service_requests.course_offering_id', 'left')
                    ->join('courses', 'courses.id = course_offerings.course_id', 'left')
                    ->join('users', 'users.id = service_requests.user_id', 'left')
                    ->where('service_requests.id', $id)
                    ->first();

                // مرفقات أولية غير مرتبطة برسالة
                $attachments = $this->requestFileModel
                    ->select('files.id, files.file_name AS name, files.file_size_bytes AS size, files.mime_type')
                    ->join('files', 'files.id = service_request_files.file_id')
                    ->where('service_request_files.service_request_id', $id)
                    ->where('service_request_files.message_id', null)
                    ->findAll();

                $payload['request']     = $request ?? null;
                $payload['attachments'] = $attachments ?? [];
            }

            // ====================== تحميل الرسائل ======================
            $messagesBuilder = $this->messageModel
                ->select(
                    'service_request_messages.id, ' .
                    'service_request_messages.service_request_id, ' .
                    'service_request_messages.sender_id, ' .
                    'service_request_messages.message_type, ' .
                    'service_request_messages.message, ' .
                    'service_request_messages.is_deleted, ' .
                    'service_request_messages.created_at, ' .
                    'users.name AS sender_name, ' .
                    'users.account_type_id, ' .
                    'account_types.name AS account_type_name'
                )
                ->join('users', 'users.id = service_request_messages.sender_id')
                ->join('account_types', 'account_types.id = users.account_type_id', 'left')
                ->where('service_request_messages.service_request_id', $id)
                ->where('service_request_messages.deleted_at', null);

            if ($beforeId > 0) {
                $messagesBuilder->where('service_request_messages.id <', $beforeId);
            }

            $messagesBuilder->orderBy('service_request_messages.id', strtoupper($order))
                            ->limit($limit + 1);

            $rows = $messagesBuilder->get()->getResultArray();

            // فحص وجود المزيد
            $hasMore = false;
            if (count($rows) > $limit) {
                $hasMore = true;
                array_pop($rows);
            }

            // في التحميل الأولي مع order=desc نعرض تصاعديًا
            if ($beforeId <= 0 && $order === 'desc') {
                $rows = array_reverse($rows);
            }

            // ====================== ملفات الرسائل (بدون شرط service_request_id) ======================
            $messageIds   = array_column($rows, 'id');
            $messageFiles = [];

            if (!empty($messageIds)) {
                $files = $this->requestFileModel
                    ->select(
                        'service_request_files.message_id, ' .
                        'files.id AS file_id, ' .
                        'files.file_name, ' .
                        'files.file_size_bytes, ' .
                        'files.mime_type'
                    )
                    ->join('files', 'files.id = service_request_files.file_id')
                    ->whereIn('service_request_files.message_id', $messageIds)
                    ->findAll();

                foreach ($files as $f) {
                    $messageId = (int) $f['message_id'];
                    $messageFiles[$messageId][] = [
                        'file_id'         => (int) $f['file_id'],
                        'file_name'       => (string) $f['file_name'],
                        'file_size_bytes' => (int) ($f['file_size_bytes'] ?? 0),
                        'mime_type'       => (string) ($f['mime_type'] ?? ''),
                        // ✅ رابط تنزيل جاهز
                        'download_url'    => site_url('student/service-requests/file/' . (int) $f['file_id']),
                    ];
                }
            }

            // ====================== تشكيل الرد ======================
            foreach ($rows as &$m) {
                $m['is_self']             = (int) ((int) $m['sender_id'] === $userId);
                $m['sender_display_name'] = $m['is_self'] ? 'أنت' : ($m['sender_name'] ?? 'مستخدم');

                // مصفوفة الملفات كاملة
                $m['files'] = $messageFiles[(int) $m['id']] ?? [];

                // توافق قديم (أول ملف فقط)
                if (!empty($m['files'])) {
                    $m['file_id']   = $m['files'][0]['file_id'];
                    $m['file_name'] = $m['files'][0]['file_name'];
                }

                if ((int) $m['is_deleted'] === 1) {
                    $m['message']   = 'تم حذف هذه الرسالة';
                    $m['file_id']   = null;
                    $m['file_name'] = null;
                    $m['files']     = [];
                }
            }
            unset($m);

            if (!empty($rows)) {
                $ids = array_map('intval', array_column($rows, 'id'));
                $payload['oldest_id'] = min($ids);
                $payload['newest_id'] = max($ids);
            }

            $payload['messages'] = $rows;
            $payload['has_more'] = $hasMore;

            return $this->response
                ->setJSON($payload)
                ->setStatusCode(ResponseInterface::HTTP_OK);
        } catch (\Throwable $e) {
            log_message('error', "Error in messages(): " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'حدث خطأ في تحميل الرسائل',
            ])->setStatusCode(500);
        }
    }

    // ---------------------------------
    // إرسال رسالة (نص/صوت/ملف) + ربط الملف
    // ---------------------------------
    public function sendMessage(int $id)
    {
        $accessCheck = $this->checkUserAccess($id);
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId = $accessCheck['user_id'];

        $request = $this->serviceRequestModel
            ->select('id, status, user_id')
            ->where('id', $id)
            ->first();

        if (!$request) {
            return $this->response->setJSON(['success' => false, 'message' => 'الطلب غير موجود']);
        }
        if (in_array($request['status'], ['closed', 'cancelled'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يمكن إرسال رسائل في طلب مغلق أو ملغي']);
        }

        $messageType = (string) $this->request->getPost('message_type');
        $rules       = ['message_type' => 'required|in_list[text,voice,file]'];
        if ($messageType === 'text') {
            $rules['message'] = 'required|string|min_length[1]';
        } elseif ($messageType === 'voice') {
            $rules['voice'] = 'uploaded[voice]'
                . '|max_size[voice,15360]'
                . '|mime_in[voice,audio/webm,video/webm,audio/ogg,video/ogg,audio/mpeg,audio/wav]'
                . '|ext_in[voice,webm,ogg,mp3,wav]';
        }
        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'البيانات غير صالحة',
                'errors'  => $this->validator->getErrors(),
            ]);
        }

        $db = \Config\Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            $messageData = [
                'service_request_id' => (int) $id,
                'sender_id'          => $userId,
                'message_type'       => $messageType,
                'is_read'            => 0,
            ];

            $fileId = null;

            if ($messageType === 'text') {
                $messageData['message'] = (string) $this->request->getPost('message');
            } elseif ($messageType === 'voice') {
                $voiceFile = $this->request->getFile('voice');
                if (!$voiceFile || !$voiceFile->isValid()) {
                    throw new \RuntimeException('ملف الصوت غير صالح');
                }
                $destDir = ROOTPATH . 'public/uploads/services/voice';
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }
                $newName = $voiceFile->getRandomName();
                $voiceFile->move($destDir, $newName);

                $fileId = (int) $this->fileModel->insert([
                    'file_name'       => 'تسجيل صوتي',
                    'file_path'       => 'uploads/services/voice/' . $newName,
                    'mime_type'       => $voiceFile->getClientMimeType(),
                    'file_size_bytes' => $voiceFile->getSize(),
                    'uploader_id'     => $userId,
                ]);
            } elseif ($messageType === 'file') {
                $files = $this->request->getFiles();
                if (empty($files['files']) || !is_array($files['files'])) {
                    throw new \RuntimeException('لم يتم رفع ملفات');
                }
                $destDir = ROOTPATH . 'public/uploads/services';
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }

                foreach ($files['files'] as $file) {
                    if ($file->isValid() && !$file->hasMoved()) {
                        $newName = $file->getRandomName();
                        $file->move($destDir, $newName);

                        $fileId = (int) $this->fileModel->insert([
                            'file_name'       => $file->getClientName(),
                            'file_path'       => 'uploads/services/' . $newName,
                            'mime_type'       => $file->getClientMimeType(),
                            'file_size_bytes' => $file->getSize(),
                            'uploader_id'     => $userId,
                        ]);
                        break; // أول ملف فقط
                    }
                }

                if (!$fileId) {
                    throw new \RuntimeException('فشل في رفع الملفات');
                }
            }

            // إدراج الرسالة
            $messageId = (int) $this->messageModel->insert($messageData);

            // ربط الملف إن وجد
            if ($fileId) {
                $this->requestFileModel->insert([
                    'service_request_id' => (int) $id,
                    'message_id'         => (int) $messageId,
                    'file_id'            => (int) $fileId,
                    'file_type'          => ($messageType === 'voice' ? 'voice' : 'document'),
                ]);
            }

            $db->transComplete();
            if (!$db->transStatus()) {
                throw new \RuntimeException('فشل في إرسال الرسالة');
            }

            // إعادة الرسالة المرسلة
            $sent = $this->messageModel
                ->select(
                    'service_request_messages.id, service_request_messages.service_request_id, service_request_messages.sender_id, ' .
                    'service_request_messages.message_type, service_request_messages.message, service_request_messages.created_at, ' .
                    'users.name AS sender_name, account_types.name AS account_type_name'
                )
                ->join('users', 'users.id = service_request_messages.sender_id')
                ->join('account_types', 'account_types.id = users.account_type_id', 'left')
                ->where('service_request_messages.id', $messageId)
                ->where('service_request_messages.deleted_at', null)
                ->first();

            if ($fileId) {
                $sent['file_id']     = $fileId;
                $fileInfo            = $this->fileModel->find($fileId);
                $sent['file_name']   = $fileInfo['file_name'] ?? null;
                // رابط تنزيل فوري للواجهة
                $sent['download_url'] = site_url('student/service-requests/file/' . (int) $fileId);
            }

            $sent['is_self']             = (int) ((int) $sent['sender_id'] === $userId);
            $sent['sender_display_name'] = $sent['is_self'] ? 'أنت' : ($sent['sender_name'] ?? 'مستخدم');

            return $this->response->setJSON(['success' => true, 'message' => $sent]);
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ---------------------------------
    // حذف رسالة (Soft Delete)
    // ---------------------------------
    public function deleteMessage(int $id)
    {
        $messageId = (int) $id;

        $row = $this->messageModel->find($messageId);
        if (!$row) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الرسالة غير موجودة.',
            ])->setStatusCode(404);
        }

        $userId = (int) (session('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يجب تسجيل الدخول.',
            ])->setStatusCode(401);
        }

        // السماح فقط لصاحب الرسالة
        if ((int) $row['sender_id'] !== $userId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'غير مصرح لك بحذف هذه الرسالة.',
            ])->setStatusCode(403);
        }

        $ok = $this->messageModel->delete($messageId);

        return $this->response->setJSON(['success' => (bool) $ok]);
    }

    // ---------------------------------
    // رسائل جديدة بعد last_id (ترتيب ASC)
    // ---------------------------------
    public function getNewMessages(int $id)
    {
        $accessCheck = $this->checkUserAccess($id);
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId = (int) $accessCheck['user_id'];
        $lastId = (int) ($this->request->getGet('last_id') ?: 0);

        $messages = $this->messageModel
            ->select(
                'service_request_messages.id, service_request_messages.service_request_id, service_request_messages.sender_id, ' .
                'service_request_messages.message_type, service_request_messages.message, service_request_messages.created_at, ' .
                'users.name AS sender_name, account_types.name AS account_type_name, files.id AS file_id, files.file_name'
            )
            ->join('users', 'users.id = service_request_messages.sender_id')
            ->join('account_types', 'account_types.id = users.account_type_id', 'left')
            ->join('service_request_files', 'service_request_files.message_id = service_request_messages.id', 'left')
            ->join('files', 'files.id = service_request_files.file_id', 'left')
            ->where('service_request_messages.service_request_id', $id)
            ->where('service_request_messages.id >', $lastId)
            ->where('service_request_messages.deleted_at', null)
            ->orderBy('service_request_messages.id', 'ASC') // 👈 ثابت
            ->findAll();

        foreach ($messages as &$m) {
            $m['is_self']             = (int) ((int) $m['sender_id'] === $userId);
            $m['sender_display_name'] = $m['is_self'] ? 'أنت' : ($m['sender_name'] ?? 'مستخدم');

            // ✅ لو فيه ملف واحد مُرفق في هذا الاستعلام، أعطِ الرابط الجاهز
            if (!empty($m['file_id'])) {
                $m['download_url'] = site_url('student/service-requests/file/' . (int) $m['file_id']);
            }
        }
        unset($m);

        return $this->response->setJSON(['success' => true, 'messages' => $messages]);
    }

    // ---------------------------------
    // عدّاد غير المقروء لكل طلب
    // ---------------------------------
    public function getUnreadCounts()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = $accessCheck['user_id'];
        $userRole = $accessCheck['user_role'];

        $ids = $this->serviceRequestModel
            ->select('id')
            ->where($userRole === 3 ? 'user_id' : '1', $userRole === 3 ? $userId : 1)
            ->findColumn('id');

        if (!$ids) {
            return $this->response->setJSON(['success' => true, 'counts' => []]);
        }

        $builder = $this->messageModel
            ->select('service_request_id, COUNT(*) AS unread_count')
            ->whereIn('service_request_id', $ids)
            ->where('is_read', 0)
            ->where('deleted_at', null);

        if ($userRole === 3) {
            $builder->where('sender_id !=', $userId);
        }

        $rows = $builder->groupBy('service_request_id')->findAll();

        $counts = array_fill_keys($ids, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['service_request_id']] = (int) $row['unread_count'];
        }

        return $this->response->setJSON(['success' => true, 'counts' => $counts]);
    }

    // ---------------------------------
    // وضع "مقروء" لكل رسائل الطلب
    // ---------------------------------
    public function markAsRead(int $id)
    {
        $accessCheck = $this->checkUserAccess($id);
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = $accessCheck['user_id'];
        $userRole = $accessCheck['user_role'];

        $builder = $this->messageModel
            ->where('service_request_id', (int) $id)
            ->where('is_read', 0)
            ->where('deleted_at', null);

        if ($userRole === 3) {
            $builder->where('sender_id !=', $userId);
        }

        $ok = $builder->set('is_read', 1)->update();

        return $this->response->setJSON(['success' => (bool) $ok]);
    }

    // ---------------------------------
    // إلغاء الطلب + رسالة نظام
    // ---------------------------------
    public function cancel(int $id)
    {
        $accessCheck = $this->checkUserAccess($id);
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId = $accessCheck['user_id'];

        $request = $this->serviceRequestModel
            ->select('id, status, user_id')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->first();

        if (!$request) {
            return $this->response->setJSON(['success' => false, 'message' => 'لا يمكن إلغاء هذا الطلب']);
        }

        $this->serviceRequestModel->update($id, [
            'status'    => 'cancelled',
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->messageModel->insert([
            'service_request_id' => $id,
            'sender_id'          => $userId,
            'message_type'       => 'text',
            'message'            => 'تم إلغاء الطلب من قبل المستخدم',
            'is_read'            => 0,
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'تم إلغاء الطلب بنجاح']);
    }

    // ✅ حذف طلب كامل (للأدمن)
    public function delete(int $id)
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userRole = $accessCheck['user_role'];
        if (!in_array($userRole, [1, 2], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'غير مصرح لك بحذف الطلبات',
            ])->setStatusCode(403);
        }

        $request = $this->serviceRequestModel->find($id);
        if (!$request) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ])->setStatusCode(404);
        }

        $deleted = $this->serviceRequestModel->delete($id);

        return $this->response->setJSON([
            'success' => (bool) $deleted,
            'message' => $deleted ? 'تم حذف الطلب بنجاح' : 'فشل في حذف الطلب',
        ]);
    }

    // ✅ إعادة فتح طلب
    public function reopen(int $id)
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userRole = $accessCheck['user_role'];
        $userId   = $accessCheck['user_id'];

        $request = $this->serviceRequestModel->find($id);
        if (!$request) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ])->setStatusCode(404);
        }

        if ($userRole === 3 && (int) $request['user_id'] !== $userId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'غير مصرح لك بإعادة فتح هذا الطلب',
            ])->setStatusCode(403);
        }

        if (!in_array($request['status'], ['closed', 'resolved', 'cancelled'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'لا يمكن إعادة فتح هذا الطلب',
            ]);
        }

        $updated = $this->serviceRequestModel->update($id, [
            'status'    => 'in_progress',
            'closed_at' => null,
        ]);

        if ($updated) {
            $this->messageModel->insert([
                'service_request_id' => $id,
                'sender_id'          => $userId,
                'message_type'       => 'text',
                'message'            => 'تم إعادة فتح الطلب',
                'is_read'            => 0,
            ]);
        }

        return $this->response->setJSON([
            'success' => (bool) $updated,
            'message' => $updated ? 'تم إعادة فتح الطلب بنجاح' : 'فشل في إعادة فتح الطلب',
        ]);
    }

    // ✅ البحث في الطلبات
    public function search()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = $accessCheck['user_id'];
        $userRole = $accessCheck['user_role'];

        $query  = trim($this->request->getGet('q') ?? '');
        $status = trim($this->request->getGet('status') ?? '');
        $limit  = max(1, min(100, (int) ($this->request->getGet('limit') ?? 20)));

        if ($query === '' && $status === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'يرجى إدخال كلمة بحث أو تحديد حالة',
            ]);
        }

        $builder = $this->serviceRequestModel
            ->select('service_requests.id, service_requests.subject, service_requests.status, service_requests.created_at, courses.name AS course_name, users.name AS user_name')
            ->join('course_offerings', 'course_offerings.id = service_requests.course_offering_id', 'left')
            ->join('courses', 'courses.id = course_offerings.course_id', 'left')
            ->join('users', 'users.id = service_requests.user_id', 'left');

        if ($userRole === 3) {
            $builder->where('service_requests.user_id', $userId);
        }

        if ($query !== '') {
            $builder->groupStart()
                ->like('service_requests.subject', $query)
                ->orLike('service_requests.description', $query)
                ->groupEnd();
        }

        if ($status !== '') {
            $builder->where('service_requests.status', $status);
        }

        $results = $builder->orderBy('service_requests.created_at', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'results' => $results,
            'count'   => count($results),
        ]);
    }

    // ---------------------------------
    // تنزيل ملف بعد التحقق من الصلاحية (مرن مع message_id)
    // ---------------------------------
public function downloadFile(int $fileId)
{
    // ✅ تحقق جلسة عام
    $accessCheck = $this->checkUserAccess();
    if (! $accessCheck['success']) {
        return $this->response
            ->setStatusCode($accessCheck['status_code'])
            ->setBody('غير مصرح');
    }

    $userId   = (int) $accessCheck['user_id'];
    $userRole = (int) $accessCheck['user_role'];

    // ✅ جلب الملف + ربطه بطلب الخدمة لأجل الصلاحيات
    $row = $this->fileModel
        ->select("
            files.id,
            files.file_name,
            files.file_path,
            files.mime_type,
            files.file_size_bytes,
            sr.id          AS request_id,
            sr.user_id     AS owner_id,
            sr.assigned_to AS assigned_to
        ")
        ->join('service_request_files srf', 'srf.file_id = files.id', 'left')
        ->join('service_request_messages srm', 'srm.id = srf.message_id', 'left')
        ->join('service_requests sr', 'sr.id = COALESCE(srf.service_request_id, srm.service_request_id)', 'left')
        ->where('files.id', (int) $fileId)
        ->first();

    if (! $row || empty($row['request_id'])) {
        // إخفاء تفاصيل عدم الأهلية/التسريب
        return $this->response->setStatusCode(404)->setBody('الملف غير موجود');
    }

    // ✅ منطق الصلاحيات حسب المطلوب
    $allowed = false;
    if ($userRole === 1) { // أدمن
        $allowed = true;
    } elseif ($userRole === 2) { // معلّم
        $allowed = ((int) ($row['assigned_to'] ?? 0) === $userId);
    } elseif ($userRole === 3) { // طالب
        $allowed = ((int) ($row['owner_id'] ?? 0) === $userId);
    }

    if (! $allowed) {
        return $this->response->setStatusCode(404)->setBody('الملف غير موجود');
    }

    // ✅ تطبيع المسار
    $dbPath = (string) ($row['file_path'] ?? '');
    $dbPath = trim($dbPath);

    // حماية من المسارات الشاذة
    $dbPath = str_replace(["\0", "..\\" , "../"], '', $dbPath);

    // لو كان المسار مطلق على السيرفر نستخدمه مباشرة
    $candidates = [];
    if ($dbPath !== '' && $dbPath[0] === '/') {
        $candidates[] = $dbPath;
    } else {
        // أزل "public/" الأولى إن وُجدت داخل القيمة المخزنة
        $relative = ltrim(preg_replace('#^public/#', '', $dbPath), '/');

        // نجرب عدة احتمالات شائعة على Hostinger/CI4
        $candidates[] = FCPATH . $relative;                  // عادة يشير إلى public/
        $candidates[] = ROOTPATH . 'public/' . $relative;    // public داخل المشروع
        $candidates[] = dirname(FCPATH) . '/' . $relative;   // مستوى أعلى من public/ (بعض حالات الرفع القديمة)
        $candidates[] = ROOTPATH . $relative;                // احتياط
    }

    $absolutePath = null;
    foreach ($candidates as $p) {
        if (is_file($p)) {
            $absolutePath = $p;
            break;
        }
    }

    if (! $absolutePath) {
        return $this->response->setStatusCode(404)->setBody('الملف غير موجود على الخادم');
    }

    // ✅ تحضير الميتاداتا
    $fileName = (string) ($row['file_name'] ?? basename($absolutePath));
    $mime     = (string) ($row['mime_type'] ?? '');
    if ($mime === '') {
        $detected = @mime_content_type($absolutePath);
        $mime = is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    // ✅ عرض Inline للأنواع التي تُشغّل داخل المتصفح
    $isInlineType = (bool) preg_match('#^(audio|image|video)/#', $mime) || $mime === 'application/pdf';

    // خيار يدوي لإجبار التحميل (?dl=1)
    if ((string) $this->request->getGet('dl') === '1') {
        $isInlineType = false;
    }

    $this->response->setHeader('X-Content-Type-Options', 'nosniff');

    if ($isInlineType) {
        $size = @filesize($absolutePath);
        if (is_int($size) && $size >= 0) {
            $this->response->setHeader('Content-Length', (string) $size);
        }

        $this->response
            ->setHeader('Content-Disposition', 'inline; filename="' . addslashes($fileName) . '"')
            ->setContentType($mime);

        $data = @file_get_contents($absolutePath);
        if ($data === false) {
            return $this->response->setStatusCode(500)->setBody('تعذر قراءة الملف');
        }

        return $this->response->setBody($data);
    }

    // 📥 غير ذلك: تحميل مباشر
    return $this->response->download($absolutePath, null)->setFileName($fileName);
}


    // ---------------------------------
    // المساقات المتاحة للطالب الحالي
    // ---------------------------------
    public function getAvailableCourses()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId = $accessCheck['user_id'];

        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->response->setJSON(['success' => false, 'message' => 'المستخدم غير موجود']);
        }

        $courses = $this->courseOfferingModel
            ->select('course_offerings.id, courses.name, courses.code')
            ->join('courses', 'courses.id = course_offerings.course_id')
            ->join('semesters', 'semesters.id = course_offerings.semester_id')
            ->where('course_offerings.university_id', $user['university_id'])
            ->where('semesters.academic_year_id', $user['academic_year_id'])
            ->orderBy('courses.name', 'ASC')
            ->findAll();

        return $this->response->setJSON(['success' => true, 'courses' => $courses]);
    }

public function file(int $id)
{
    // توجيه مباشر للدالة الصحيحة
    return $this->downloadFile($id);
}


    // ---------------------------------
    // إجمالي غير المقروء
    // ---------------------------------
    public function getUnreadCount()
    {
        $accessCheck = $this->checkUserAccess();
        if (!$accessCheck['success']) {
            return $this->response
                ->setJSON($accessCheck)
                ->setStatusCode($accessCheck['status_code']);
        }

        $userId   = $accessCheck['user_id'];
        $userRole = $accessCheck['user_role'];

        $builder = $this->serviceRequestModel->select('id');

        if ($userRole === 3) {
            $builder->where('user_id', $userId)->where('status !=', 'closed');
        }

        $ids = $builder->findColumn('id');

        if (!$ids) {
            return $this->response->setJSON(['success' => true, 'count' => 0]);
        }

        $builder = $this->messageModel
            ->select('COUNT(*) AS c')
            ->whereIn('service_request_id', $ids)
            ->where('is_read', 0)
            ->where('deleted_at', null);

        if ($userRole === 3) {
            $builder->where('sender_id !=', $userId);
        }

        $row = $builder->first();

        return $this->response->setJSON([
            'success' => true,
            'count'   => (int) ($row['c'] ?? 0),
        ]);
    }
}
