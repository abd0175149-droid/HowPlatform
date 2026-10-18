<?php

declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Files\File;

/**
 * كنترولر طلبات الخدمة للطلاب
 * - meta(): جلب مساقات الطالب بناءً على diploma_users -> university_id, academic_year_id
 * - store(): إنشاء طلب خدمة جديد + رفع المرفقات + تحديد أولوية حسب أعلى خطة فعّالة من user_subscriptions
 */
class ServiceRequestsController extends BaseController
{
    /** @var BaseConnection */
    protected $db;

    /** حجم الملف الأقصى (10MB) */
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    /** أنواع المرفقات المقبولة */
    private const ALLOWED_MIME = [
        'image/jpeg','image/png','image/gif','image/webp',
        'video/mp4','video/webm','video/ogg',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-rar-compressed'
    ];

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * جلب مساقات الطالب (لعرضها في Select داخل المودال)
     * يعتمد على user_id من السيشن → diploma_users → university_id, academic_year_id
     * ثم course_offerings (university_id) + semesters (academic_year_id)
     */
    public function meta(): ResponseInterface
    {
        // 1) تحقق من الجلسة
        $userId = (int) session('user_id');
        if ($userId <= 0) {
            return $this->failJSON('غير مسجل دخول');
        }

        // 2) جلب صف الطالب من diploma_users
        $stu = $this->db->table('diploma_users')
            ->select('user_id, university_id, academic_year_id')
            ->where('user_id', $userId)
            ->get()->getRowArray();

        if (!$stu) {
            return $this->okJSON([
                'courses' => [],
                'note'    => 'لا توجد بيانات للطالب في diploma_users'
            ]);
        }

        $universityId   = (int) ($stu['university_id'] ?? 0);
        $academicYearId = (int) ($stu['academic_year_id'] ?? 0);

        // 3) جلب عروض المساقات لهذا الطالب
        // course_offerings (university_id) + join semesters (match academic_year_id)
        $courses = $this->db->table('course_offerings co')
            ->select("
                co.id            AS offering_id,
                c.id             AS course_id,
                c.name           AS course_name,
                s.id             AS semester_id,
                s.name           AS semester_name
            ")
            ->join('courses c', 'c.id = co.course_id')
            ->join('semesters s', 's.id = co.semester_id')
            ->where('co.university_id', $universityId)
            ->where('s.academic_year_id', $academicYearId)
            ->orderBy('c.name', 'ASC')
            ->get()->getResultArray();

        return $this->okJSON(['courses' => $courses]);
    }

    /**
     * إنشاء طلب خدمة جديد + رفع المرفقات
     * FormData: subject, description, course_offering_id (optional), files[]
     */
    public function store(): ResponseInterface
    {
        // 1) تحقق من الجلسة
        $userId = (int) session('user_id');
        if ($userId <= 0) {
            return $this->failJSON('غير مسجل دخول');
        }

        // 2) قراءة المدخلات
        $subject           = trim((string) $this->request->getPost('subject'));
        $description       = trim((string) $this->request->getPost('description'));
        $courseOfferingId  = (int) ($this->request->getPost('course_offering_id') ?? 0);

        // 3) فاليديشن بسيطة
        $errors = [];
        if ($subject === '' || mb_strlen($subject) < 3) {
            $errors['subject'] = 'الرجاء كتابة موضوع واضح (3 أحرف على الأقل).';
        }
        if ($description === '' || mb_strlen($description) < 10) {
            $errors['description'] = 'الرجاء كتابة تفاصيل كافية (10 أحرف على الأقل).';
        }
        if (!empty($errors)) {
            return $this->failJSON('حقول غير صحيحة', 422, ['fields' => $errors]);
        }

        // 4) حساب أولوية الطلب من خطة المستخدم الفعّالة
        $priority = $this->decidePriorityFromSubscription($userId);

        // 5) إنشاء الطلب داخل معاملة
        $this->db->transStart();

        $reqId = $this->insertServiceRequest($userId, $courseOfferingId, $subject, $description, $priority);

        // 6) حفظ المرفقات (إن وجدت)
        $savedFiles = $this->handleUploadsForRequest($reqId, $userId);

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return $this->failJSON('فشل حفظ الطلب، حاول لاحقًا.');
        }

        // رد فَطين الأولي (غير قاتل): يُخزَّن في MySQL كرسالة AI ثم إشارة لحظية
        try {
            $studentName = (string) (session('user_name') ?? '');
            $aiText      = \App\Libraries\AiReply::serviceFirstReply($subject, $description, $studentName);
            if ($aiText !== null && $aiText !== '') {
                $this->db->table('service_messages')->insert([
                    'service_request_id' => $reqId,
                    'sender_user_id'     => null,   // فَطين (لا مستخدم بشري)
                    'sender_role'        => 'ai',
                    'body'               => $aiText,
                    'created_at'         => date('Y-m-d H:i:s'),
                ]);
                $aiMsgId = (int) $this->db->insertID();
                \App\Libraries\ChatSignal::bump($reqId, $aiMsgId, 'ai', $userId, null);
                \App\Libraries\NotificationService::push(
                    $userId,
                    'رد جديد من فَطين على طلبك',
                    \App\Libraries\NotificationService::snippet($aiText),
                    '#/service'
                );
            }
        } catch (\Throwable $e) {
            log_message('error', 'Service AI first reply failed for request ' . $reqId . ': ' . $e->getMessage());
        }

        // إشعار الآدمن بطلب خدمة جديد
        \App\Libraries\NotificationService::pushToAdmins(
            'طلب خدمة جديد',
            'طلب جديد: ' . $subject,
            '#service_chat'
        );

        return $this->okJSON([
            'request_id' => $reqId,
            'priority'   => $priority,
            'files'      => $savedFiles
        ], 'تم إرسال طلبك بنجاح');
    }

    /* ========================= أدوات داخلية ========================= */

    /** حوّل level إلى priority */
    private function levelToPriority(int $level): string
    {
        if ($level >= 3) return 'urgent';
        if ($level === 2) return 'high';
        if ($level === 1) return 'medium';
        return 'low';
    }

    /**
     * استنتاج أولوية الطلب من جدول user_subscriptions (أعلى خطة فعّالة الآن)
     * - نتوقع وجود أعمدة: user_id, plan_level, starts_at, ends_at, status
     * - إن لم توجد/لا نتائج: نرجع level=0
     */
  private function decidePriorityFromSubscription(int $userId): string
{
    // وضع api: مستوى الخطّة من Plans (entitlements) مع fail-open للـ SQL المحلّي.
    try {
        $client = new \App\Libraries\PlansClient();
        if ($client->entitlementsMode() === 'api') {
            $ent = $client->entitlements($userId);
            if (is_array($ent)) {
                if (! ($ent['has_plan'] ?? false)) {
                    return 'low';
                }
                $level = (int) ($ent['plan']['level'] ?? 0);
                return $this->levelToPriority($level);
            }
            // Plans غير متاح → تابع للقراءة المحلّية (fail-open)
        }
    } catch (\Throwable $e) {
        log_message('warning', 'ServiceRequests::decidePriority Plans failed: ' . $e->getMessage());
    }

    // لو الجدولين غير موجودين، نعطي أولوية منخفضة بدون كسر النظام
    if (!$this->tableExists('user_subscriptions') || !$this->tableExists('plans')) {
        return 'low';
    }

    // أعلى ليفل لخطة فعّالة الآن (status='active' وداخل الفترة)
    // ملاحظة: الأعمدة الصحيحة حسب ملف SQL: start_at, end_at, status, plan_id
    $sql = "
        SELECT MAX(p.level) AS max_level
        FROM user_subscriptions us
        JOIN plans p ON p.id = us.plan_id
        WHERE us.user_id = ?
          AND us.status = 'active'
          AND us.start_at <= NOW()
          AND (us.end_at IS NULL OR us.end_at >= NOW())
    ";

    $query = $this->db->query($sql, [$userId]);
    if ($query === false) {
        return 'low'; // دفاعي: في حال أي خطأ SQL
    }

    $row = $query->getRowArray();
    $maxLevel = (int) ($row['max_level'] ?? 0);

    // تحويل المستوى إلى أولوية
    if ($maxLevel >= 3) return 'urgent';
    if ($maxLevel === 2) return 'high';
    if ($maxLevel === 1) return 'medium';
    return 'low';
}


    /** إدراج سجل الطلب في service_requests */
    private function insertServiceRequest(
        int $userId,
        int $courseOfferingId,
        string $subject,
        string $description,
        string $priority
    ): int {
        $data = [
            'user_id'           => $userId,
            'course_offering_id'=> $courseOfferingId ?: null,
            'subject'           => $subject,
            'description'       => $description,
            'priority'          => $priority,
            'status'            => 'pending',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        $this->db->table('service_requests')->insert($data);
        return (int) $this->db->insertID();
    }

    /**
     * رفع المرفقات وتسجيلها في files ثم ربطها بالطلب في service_request_files
     */
/**
 * رفع المرفقات وتسجيلها في files ثم ربطها بالطلب في service_request_files
 * يعتمد أعمدة جدول files كما زوّدتني بها.
 */
private function handleUploadsForRequest(int $reqId, int $uploaderId): array
{
    $saved = [];
    $all   = $this->request->getFiles();

    // front-end يرسل بالاسم files[]
    $uploadedList = [];
    if (isset($all['files'])) {
        $uploadedList = is_array($all['files']) ? $all['files'] : [$all['files']];
    } else {
        return $saved; // لا ملفات
    }

    // مسار الحفظ (آمن). إن رغبت لاحقًا بالنشر العام، انقل لـ FCPATH.
    $uploadDir = WRITEPATH . 'uploads/service_requests/' . $reqId;
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        // لو فشل الإنشاء، نعيد مصفوفة فارغة (المعاملة ستُفشل الأعلى)
        return $saved;
    }

    $order = 0;

    foreach ($uploadedList as $file) {
        if (!$file->isValid()) {
            continue;
        }

        // تحقق الحجم والنوع
        if ($file->getSize() > self::MAX_FILE_BYTES) {
            continue;
        }
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            continue;
        }

        // حفظ الملف باسم عشوائي آمن
        $newName      = $file->getRandomName();
        $moved        = $file->move($uploadDir, $newName, true);
        if (!$moved) {
            continue;
        }

        $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $newName;

        // إدراج في جدول files — أسماء الأعمدة مطابقة تمامًا لجدولك
        $filesRow = [
            'file_name'        => $file->getClientName(),
            'file_path'        => $absolutePath,                 // مسار التخزين على الخادم
            'mime_type'        => $mime,                         // نوع الملف
            'file_size_bytes'  => (int) $file->getSize(),        // الحجم بالبايت
            'uploader_id'      => $uploaderId,                   // FK إلى diploma_users.user_id
            'description'      => null,                          // اختياري
            'uploaded_at'      => date('Y-m-d H:i:s'),
            'is_premium'       => 0,                             // حسب منطقك الافتراضي
            'allow_public_view'=> null,                          // NULL = سياسات الوصول الداخلية
        ];

        $this->db->table('files')->insert($filesRow);
        $fileId = (int) $this->db->insertID();

        // ربط الملف بالطلب
        $this->db->table('service_request_files')->insert([
            'service_request_id' => $reqId,
            'message_id'         => null,
            'file_id'            => $fileId,
            'file_type'          => 'attachment',
            'display_order'      => $order++,
        ]);

        // إرجاع بيانات متسقة للاستخدام في الواجهة (أسماء المفاتيح مطابقة)
        $saved[] = [
            'file_id'         => $fileId,
            'file_name'       => $filesRow['file_name'],
            'file_size_bytes' => $filesRow['file_size_bytes'],
            'mime_type'       => $filesRow['mime_type'],
        ];
    }

    return $saved;
}


    /** فحص وجود جدول */
    private function tableExists(string $table): bool
    {
        return (bool) $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table))->getResult();
    }

    /* ========================= ردود JSON موحّدة ========================= */

    private function okJSON(array $data = [], string $message = 'OK'): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ])->setStatusCode(200);
    }

    private function failJSON(string $message, int $status = 400, array $extra = []): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $message,
        ] + $extra)->setStatusCode($status);
    }
}
