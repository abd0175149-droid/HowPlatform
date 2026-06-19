<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Helpers\StudentViewHelper;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\TaskModel;
use App\Models\TaskSpecialtyModel;
use App\Models\TaskFileModel;
use App\Models\FileModel;

class StuDashboard extends BaseController
{
    protected TaskModel          $taskModel;
    protected TaskSpecialtyModel $taskSpecModel;
    protected TaskFileModel      $taskFileModel;
    protected FileModel          $fileModel;

    public function __construct()
    {
        $this->taskModel     = new TaskModel();
        $this->taskSpecModel = new TaskSpecialtyModel();
        $this->taskFileModel = new TaskFileModel();
        $this->fileModel     = new FileModel();
    }

    /* استخراج معرّف المستخدم من الجلسة (متسامح مع أكثر من مفتاح) */
    private function currentUserId(): ?int
    {
        // استخدام الإعدادات الفعالة للأدمن
        $uid = StudentViewHelper::getEffectiveUserId();
        if ($uid) {
            return $uid;
        }

        $authUser = session('auth_user') ?: [];
        $uid      = $authUser['id'] ?? null;

        if (!$uid) {
            $uid = (int) (session('user_id') ?? 0);
            if ($uid <= 0) {
                return null;
            }
        }
        return (int) $uid;
    }

    /* جلب بروفايل الطالب من diploma_users أو الإعدادات الفعالة */
    private function fetchDiplomaProfile(int $userId): ?array
    {
        // إذا كان الأدمن في وضع الاستعراض، استخدم الإعدادات الفعالة
        if (StudentViewHelper::isAdminViewingAsStudent()) {
            return [
                'user_id' => $userId,
                'university_id' => StudentViewHelper::getEffectiveUniversityId(),
                'specialty_id' => StudentViewHelper::getEffectiveSpecialtyId(),
                'academic_year_id' => StudentViewHelper::getEffectiveAcademicYearId(),
                'diploma_type_id' => StudentViewHelper::getEffectiveDiplomaTypeId(),
                'is_premium' => 0,
                'premium_until' => null,
                'is_complete' => 1,
            ];
        }

        $db = db_connect();
        $row = $db->table('diploma_users')
            ->select([
                'user_id',
                'university_id',
                'specialty_id',
                'academic_year_id',
                'diploma_type_id',
                'is_premium',
                'premium_until',
                'is_complete',
            ])
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /* هل المستخدم Premium حالياً؟ يعتمد diploma_users */
    private function isStudentPremium(int $userId): bool
    {
        $profile = $this->fetchDiplomaProfile($userId);
        if (!$profile) return false;

        // إن كان is_premium=1 ونهاية الاشتراك (إن وُجِدت) لم تنتهِ
        if ((int)($profile['is_premium'] ?? 0) === 1) {
            if (!empty($profile['premium_until'])) {
                return (strtotime((string)$profile['premium_until']) >= time());
            }
            return true;
        }
        return false;
    }

    /* ======================== المهام القادمة ======================== */
    public function upcomingTasks(): ResponseInterface
    {
        try {
            $uid = $this->currentUserId();
            if (!$uid) {
                // غير مُسجّل
                return $this->response->setStatusCode(401)->setJSON([
                    'message' => 'يجب تسجيل الدخول.',
                ]);
            }

            $profile = $this->fetchDiplomaProfile($uid);
            if (!$profile) {
                // لا يوجد صف في diploma_users للطالب
                return $this->response->setJSON([
                    'tasks'  => [],
                    'reason' => 'no_profile', // الواجهة ستعرض تنبيه مناسب
                    'message'=> 'لم يتم العثور على ملف الطالب في المنصة.',
                ]);
            }

            // إن كان غير مكتمل البيانات (جامعات/تخصص/سنة... إلخ)
            if (
                empty($profile['university_id']) ||
                empty($profile['academic_year_id']) ||
                (
                    // إن كانت المهمة قد تكون مقيّدة بالتخصص، نفضّل توفّر specialty_id
                    is_null($profile['specialty_id'])
                )
            ) {
                return $this->response->setJSON([
                    'tasks'  => [],
                    'reason' => 'profile_incomplete',
                    'message'=> 'الرجاء إكمال الملف الشخصي لاستخدام لوحة الطالب.',
                ]);
            }

            $db = db_connect();

            // استخدام الإعدادات الفعالة
            $universityId = StudentViewHelper::getEffectiveUniversityId() ?? $profile['university_id'];
            $academicYearId = StudentViewHelper::getEffectiveAcademicYearId() ?? $profile['academic_year_id'];
            $specialtyId = StudentViewHelper::getEffectiveSpecialtyId() ?? $profile['specialty_id'];

            // ملاحظة: نقيّد بالمهام الظاهرة حالياً (visible_from_date <= NOW())
            $sql = "
                SELECT 
                    t.id,
                    t.title,
                    t.description,
                    t.due_date,
                    wc.week_number AS week_number,        -- ✅ نحتاجه للرابط
                    co.id         AS course_offering_id,  -- ✅ نحتاجه للرابط
                    c.name        AS course_name
                FROM tasks t
                JOIN weekly_contents wc       ON wc.id = t.weekly_content_id
                JOIN course_offerings co      ON co.id = wc.course_offering_id
                JOIN courses c                ON c.id = co.course_id
                JOIN semesters s              ON s.id = co.semester_id
                LEFT JOIN task_specialties ts ON ts.task_id = t.id
                WHERE 
                    co.university_id = ?
                    AND s.academic_year_id = ?
                    AND (
                        t.is_for_all_specialties = 1 
                        OR ? IS NULL
                        OR ts.specialty_id = ?
                    )
                    AND (t.visible_from_date IS NULL OR t.visible_from_date <= NOW())
                    AND (t.due_date IS NULL OR t.due_date >= NOW())
                GROUP BY t.id
                ORDER BY t.due_date IS NULL, t.due_date ASC, t.id DESC
                LIMIT 10;
            ";


            $query = $db->query($sql, [$universityId, $academicYearId, $specialtyId, $specialtyId]);
            if (!$query) {
                return $this->response->setStatusCode(500)->setJSON([
                    'message' => 'فشل الاستعلام',
                    'error'   => $db->error(),
                ]);
            }

            $tasks = $query->getResultArray(); // نُعيد كمصفوفة

            return $this->response->setStatusCode(200)->setJSON($tasks);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'message' => '❌ استثناء داخلي',
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /* ======================== تفاصيل مهمة للعرض ======================== */
    public function getTaskForView(int $id): ResponseInterface
    {
        try {
            $task = $this->taskModel
            ->select([
                'tasks.id                        AS id',
                'tasks.title                     AS title',
                'tasks.description               AS description',
                'tasks.visible_from_date',
                'tasks.due_date',
                'tasks.is_for_all_specialties',
                'task_types.name                 AS task_type',
                'weekly_contents.week_number     AS week_number',
                'course_offerings.id             AS course_offering_id',  // ✅ جديد
                'courses.name                    AS course',
                'universities.id                 AS university_id',
                'universities.name               AS university',
                'semesters.name                  AS semester',
                'academic_years.label            AS academic_year',
            ])
                ->join('task_types',       'task_types.id       = tasks.task_type_id',       'left')
                ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id')
                ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
                ->join('courses',          'courses.id          = course_offerings.course_id')
                ->join('universities',     'universities.id     = course_offerings.university_id')
                ->join('semesters',        'semesters.id        = course_offerings.semester_id')
                ->join('academic_years',   'academic_years.id   = semesters.academic_year_id')
                ->where('tasks.id', $id)
                ->first();

            if (!$task) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON(['message' => 'المهمة غير موجودة']);
            }

            $specialties = $this->taskSpecModel
                ->select('specialties.id, specialties.name')
                ->join('specialties', 'specialties.id = task_specialties.specialty_id')
                ->where('task_specialties.task_id', $id)
                ->findAll();

            $files = $this->taskFileModel
                ->select([
                    'files.id        AS file_id',
                    'files.file_name',
                    'files.file_path',
                    'files.description',
                    'CAST(files.is_premium AS UNSIGNED) AS is_premium',
                ])
                ->join('files', 'files.id = task_files.file_id')
                ->where('task_files.task_id', $id)
                ->orderBy('task_files.display_order', 'ASC')
                ->findAll();

            return $this->response->setJSON([
                'task'          => $task,
                'specialties'   => array_map(static fn($s) => $s['name'], $specialties),
                'specialty_ids' => array_map(static fn($s) => $s['id'],   $specialties),
                'files'         => $files,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getTaskForView error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'message' => '❌ خطأ داخلي: ' . $e->getMessage(),
            ]);
        }
    }

    /* ======================== تنزيل ملف المهمة ======================== */
    public function download(int $fileId): ResponseInterface
    {
        $file = $this->fileModel->find($fileId);
        if (!$file) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود');
        }

        // ✅ التحقق من اشتراك الطالب (يعتمد diploma_users)
        $uid = $this->currentUserId();
        if (!$uid) {
            return $this->response->setStatusCode(401)->setBody('يجب تسجيل الدخول');
        }

        $isStudentPremium = $this->isStudentPremium($uid);
        if ((int)($file['is_premium'] ?? 0) === 1 && !$isStudentPremium) {
            return $this->response->setStatusCode(403)->setBody('متاح فقط للمشتركين المميزين');
        }

        $filePath = ROOTPATH . 'public' . $file['file_path'];
        if (!is_file($filePath)) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود على الخادم');
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'inline; filename="' . $file['file_name'] . '"')
            ->setBody(file_get_contents($filePath));
    }
}
