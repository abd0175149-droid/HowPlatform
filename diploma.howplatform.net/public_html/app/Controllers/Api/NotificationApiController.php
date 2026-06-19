<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\FcmLibrary;
use CodeIgniter\HTTP\ResponseInterface;

class NotificationApiController extends BaseController
{
    private FcmLibrary $fcm;
    private $db;

    public function __construct()
    {
        $this->fcm = new FcmLibrary();
        $this->db = \Config\Database::connect();
    }

    private function json($data, int $code = 200): ResponseInterface
    {
        return $this->response->setJSON($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * إرسال إشعار عند إضافة مهمة جديدة
     * POST /api/notifications/send-task
     * 
     * Body:
     * {
     *   "task_id": 123,
     *   "title": "مهمة جديدة",
     *   "body": "تم إضافة مهمة جديدة في المساق"
     * }
     */
    public function sendTaskNotification(): ResponseInterface
    {
        $post = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $taskId = (int) ($post['task_id'] ?? 0);
        $title = (string) ($post['title'] ?? 'مهمة جديدة');
        $body = (string) ($post['body'] ?? 'تم إضافة مهمة جديدة');

        if ($taskId <= 0) {
            return $this->json(['success' => false, 'message' => 'Task ID is required'], 400);
        }

        // جلب معلومات المهمة
        $task = $this->db->table('tasks')
            ->select('tasks.*, weekly_contents.course_offering_id, course_offerings.university_id, semesters.academic_year_id')
            ->join('weekly_contents', 'weekly_contents.id = tasks.weekly_content_id')
            ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
            ->join('semesters', 'semesters.id = course_offerings.semester_id')
            ->where('tasks.id', $taskId)
            ->get()
            ->getRowArray();

        if (!$task) {
            return $this->json(['success' => false, 'message' => 'Task not found'], 404);
        }

        // جلب المستخدمين المؤهلين (حسب الجامعة/السنة/التخصص)
        $universityId = (int) $task['university_id'];
        $academicYearId = (int) $task['academic_year_id'];
        $isForAllSpecialties = (int) ($task['is_for_all_specialties'] ?? 0);

        // جلب التخصصات المرتبطة بالمهمة
        $taskSpecialties = $this->db->table('task_specialties')
            ->where('task_id', $taskId)
            ->get()
            ->getResultArray();
        $specialtyIds = array_column($taskSpecialties, 'specialty_id');

        // بناء استعلام لجلب المستخدمين المؤهلين
        $query = $this->db->table('diploma_users as du')
            ->select('DISTINCT ud.push_token')
            ->join('user_devices as ud', 'ud.user_id = du.user_id', 'inner')
            ->where('du.university_id', $universityId)
            ->where('du.academic_year_id', $academicYearId)
            ->where('ud.push_token IS NOT NULL')
            ->where('ud.push_token !=', '');

        if (!$isForAllSpecialties && !empty($specialtyIds)) {
            $query->whereIn('du.specialty_id', $specialtyIds);
        }

        $devices = $query->get()->getResultArray();
        $tokens = array_filter(array_column($devices, 'push_token'));

        if (empty($tokens)) {
            return $this->json([
                'success' => true,
                'message' => 'No devices found for this task',
                'sent' => 0,
            ]);
        }

        // إرسال الإشعارات
        $result = $this->fcm->sendToMultipleDevices(
            $tokens,
            $title,
            $body,
            [
                'type' => 'task',
                'task_id' => $taskId,
                'course_offering_id' => (int) $task['course_offering_id'],
            ]
        );

        // حفظ الإشعارات في قاعدة البيانات
        $userIds = $this->db->table('diploma_users as du')
            ->select('DISTINCT du.user_id')
            ->where('du.university_id', $universityId)
            ->where('du.academic_year_id', $academicYearId);

        if (!$isForAllSpecialties && !empty($specialtyIds)) {
            $userIds->whereIn('du.specialty_id', $specialtyIds);
        }

        $users = $userIds->get()->getResultArray();
        $notificationData = [];

        foreach ($users as $user) {
            $notificationData[] = [
                'user_id' => (int) $user['user_id'],
                'notification_type_id' => 1, // نوع: مهمة جديدة
                'title' => $title,
                'message' => $body,
                'redirect_url' => '/tasks/' . $taskId,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($notificationData)) {
            $this->db->table('notifications')->insertBatch($notificationData);
        }

        return $this->json([
            'success' => true,
            'message' => 'Notifications sent',
            'sent' => $result['success'],
            'failed' => $result['failed'],
            'total' => $result['total'],
        ]);
    }

    /**
     * إرسال إشعار مخصص من الأدمن
     * POST /api/notifications/send-custom
     * 
     * Body:
     * {
     *   "title": "عنوان الإشعار",
     *   "body": "نص الإشعار",
     *   "target_type": "all|university|specialty",
     *   "university_id": 1,  // اختياري
     *   "specialty_id": 2,   // اختياري
     *   "user_ids": [1,2,3], // اختياري - قائمة IDs محددة
     *   "data": {}           // اختياري - بيانات إضافية
     * }
     */
    public function sendCustomNotification(): ResponseInterface
    {
        $post = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $title = (string) ($post['title'] ?? '');
        $body = (string) ($post['body'] ?? '');
        $targetType = (string) ($post['target_type'] ?? 'all');
        $universityId = (int) ($post['university_id'] ?? 0);
        $specialtyId = (int) ($post['specialty_id'] ?? 0);
        $userIds = $post['user_ids'] ?? [];
        $data = $post['data'] ?? [];

        if (empty($title) || empty($body)) {
            return $this->json(['success' => false, 'message' => 'Title and body are required'], 400);
        }

        // تحديد المستخدمين المستهدفين
        $query = $this->db->table('diploma_users as du')
            ->select('DISTINCT du.user_id, ud.push_token')
            ->join('user_devices as ud', 'ud.user_id = du.user_id', 'inner')
            ->where('ud.push_token IS NOT NULL')
            ->where('ud.push_token !=', '');

        if (!empty($userIds) && is_array($userIds)) {
            // إرسال لمستخدمين محددين
            $query->whereIn('du.user_id', array_map('intval', $userIds));
        } elseif ($targetType === 'university' && $universityId > 0) {
            // إرسال لجامعة محددة
            $query->where('du.university_id', $universityId);
            if ($specialtyId > 0) {
                $query->where('du.specialty_id', $specialtyId);
            }
        } elseif ($targetType === 'specialty' && $specialtyId > 0) {
            // إرسال لتخصص محدد
            $query->where('du.specialty_id', $specialtyId);
        }
        // else: all - لا نضيف شروط إضافية

        $devices = $query->get()->getResultArray();
        $tokens = array_filter(array_column($devices, 'push_token'));
        $targetUserIds = array_unique(array_column($devices, 'user_id'));

        if (empty($tokens)) {
            return $this->json([
                'success' => true,
                'message' => 'No devices found',
                'sent' => 0,
            ]);
        }

        // إرسال الإشعارات
        $notificationData = array_merge([
            'type' => 'custom',
        ], $data);

        $result = $this->fcm->sendToMultipleDevices(
            $tokens,
            $title,
            $body,
            $notificationData
        );

        // حفظ الإشعارات في قاعدة البيانات
        $notifications = [];
        foreach ($targetUserIds as $userId) {
            $notifications[] = [
                'user_id' => (int) $userId,
                'notification_type_id' => 2, // نوع: إشعار مخصص
                'title' => $title,
                'message' => $body,
                'redirect_url' => $data['redirect_url'] ?? null,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($notifications)) {
            $this->db->table('notifications')->insertBatch($notifications);
        }

        return $this->json([
            'success' => true,
            'message' => 'Notifications sent',
            'sent' => $result['success'],
            'failed' => $result['failed'],
            'total' => $result['total'],
            'users_targeted' => count($targetUserIds),
        ]);
    }
}

