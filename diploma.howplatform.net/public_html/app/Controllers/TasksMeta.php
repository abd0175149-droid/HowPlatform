<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use App\Models\AcademicYearModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\SemesterModel;
use App\Models\CourseOfferingModel;
use App\Models\WeeklyContentModel;
use App\Models\TaskModel;
use App\Models\TaskTypeModel;
use App\Models\TaskSpecialtyModel;
use App\Models\TaskFileModel;
use App\Models\FileModel;
use App\Models\SpecialtyModel;
use App\Models\UniversitySpecialtyModel;


class TasksMeta extends BaseController
{
    /* --------------------------------------------------------------------
     *                         خصائص النماذج
     * ------------------------------------------------------------------ */
    protected AcademicYearModel           $yearModel;
    protected UniversityAcademicYearModel $uniYearModel;
    protected SemesterModel               $semesterModel;
    protected CourseOfferingModel         $offeringModel;
    protected WeeklyContentModel          $weeklyModel;
    protected TaskModel                   $taskModel;
    protected TaskTypeModel               $taskTypeModel;
    protected TaskSpecialtyModel          $taskSpecModel;
    protected TaskFileModel               $taskFileModel;
    protected FileModel                   $fileModel;
    protected SpecialtyModel              $specialtyModel;
    protected UniversitySpecialtyModel    $uniSpecModel;

    /* --------------------------------------------------------------------
     *                     مسارات رفع الملفات
     * ------------------------------------------------------------------ */
    private string $relUpload = '/uploads/tasks/';
    private string $absUpload;

    public function __construct()
    {
        /* تهيئة النماذج */
        $this->yearModel      = new AcademicYearModel();
        $this->uniYearModel   = new UniversityAcademicYearModel();
        $this->semesterModel  = new SemesterModel();
        $this->offeringModel  = new CourseOfferingModel();
        $this->weeklyModel    = new WeeklyContentModel();
        $this->taskModel      = new TaskModel();
        $this->taskTypeModel  = new TaskTypeModel();
        $this->taskSpecModel  = new TaskSpecialtyModel();
        $this->taskFileModel  = new TaskFileModel();
        $this->fileModel      = new FileModel();
        $this->specialtyModel = new SpecialtyModel();
        $this->uniSpecModel   = new UniversitySpecialtyModel();

        /* إنشاء مجلد الرفع إن لزم */
        $this->absUpload = rtrim(FCPATH, DIRECTORY_SEPARATOR) . $this->relUpload;
        if (! is_dir($this->absUpload)) {
            mkdir($this->absUpload, 0o775, true);
        }
    }

    /* ====================================================================
     *                         End-Points مساعِدة
     * ===================================================================*/

    public function index(): string
    {
        return view('tasks/index');
    }

    public function getAcademicYears(): ResponseInterface
    {
        return $this->json($this->yearModel->findAll());
    }

    public function getTaskTypes(): ResponseInterface
    {
        return $this->json($this->taskTypeModel->findAll());
    }

   /* public function getSpecialties(): ResponseInterface
    {
        return $this->json($this->specialtyModel->findAll());
    }*/
    public function getSpecialties($univId = null)
    {
        // إذا جاء معرّف الجامعة، جلب التخصصات المرتبطة فقط
        if ($univId) {
            // أولاً: جلب الصفوف الوسيطة لتحديد specialty_id
            $rows = $this->uniSpecModel
                         ->where('university_id', $univId)
                         ->findAll();
            $ids  = array_column($rows, 'specialty_id');

            // ثم جلب بيانات التخصصات
            $specs = $this->specialtyModel
                          ->whereIn('id', $ids)
                          ->orderBy('name','ASC')
                          ->findAll();
        } else {
            // بدون فلتر: جلب الكل
            $specs = $this->specialtyModel
                          ->orderBy('name','ASC')
                          ->findAll();
        }

        return $this->response->setJSON($specs);
    }

    public function getUniversities(int $yearId): ResponseInterface
    {
        $rows = $this->uniYearModel
            ->select('universities.id, universities.name')
            ->join('universities', 'universities.id = university_academic_years.university_id')
            ->where('academic_year_id', $yearId)
            ->findAll();

        return $this->json($rows);
    }

    public function getSemesters(int $yearId): ResponseInterface
    {
        return $this->json(
            $this->semesterModel->where('academic_year_id', $yearId)->findAll()
        );
    }

    public function getCourses(): ResponseInterface
    {
        $univId = $this->request->getGet('univId');
        $semId  = $this->request->getGet('semId');

        $rows = $this->offeringModel
            ->select('course_offerings.id, courses.name')
            ->join('courses', 'courses.id = course_offerings.course_id')
            ->where('university_id', $univId)
            ->where('semester_id', $semId)
            ->findAll();

        return $this->json($rows);
    }

    public function getWeeks(int $offeringId): ResponseInterface
    {
        return $this->json(
            $this->weeklyModel->where('course_offering_id', $offeringId)->findAll()
        );
    }

    /* ====================================================================
     *                     جلب قائمة المهام (Pagination + فلاتر)
     * ===================================================================*/
    public function getTasks(): ResponseInterface
    {
        /* معاملات الطلب */
        $weekId   = $this->request->getGet('weekId');
        $specId   = $this->request->getGet('specId');
        $qTitle   = trim((string) ($this->request->getGet('qTitle') ?? ''));
        $qSpec    = trim((string) ($this->request->getGet('qSpec')  ?? ''));
        $dateFrom = $this->request->getGet('dateFrom');
        $dateTo   = $this->request->getGet('dateTo');

        $page   = max(1, (int) ($this->request->getGet('page')  ?? 1));
        $limit  = max(1, (int) ($this->request->getGet('limit') ?? 10));
        $offset = ($page - 1) * $limit;

        /* بناء الاستعلام الأساسى */
        $builder = $this->taskModel->select([
                'tasks.id                     AS id',
                'weekly_contents.week_number  AS week_number',
                'courses.name                 AS course',
                'universities.name            AS university',
                'task_types.name              AS task_type',
                'tasks.title                  AS title',
                'tasks.description            AS description',
                'tasks.visible_from_date',
                'tasks.due_date',
                'tasks.is_for_all_specialties',
                'tasks.task_type_id',
            ])
            ->join('weekly_contents',   'weekly_contents.id = tasks.weekly_content_id')
            ->join('course_offerings',  'course_offerings.id = weekly_contents.course_offering_id')
            ->join('universities',      'universities.id = course_offerings.university_id')
            ->join('courses',           'courses.id = course_offerings.course_id')
            ->join('semesters',         'semesters.id = course_offerings.semester_id')
            ->join('academic_years',    'academic_years.id = semesters.academic_year_id')
            ->join('task_types',        'task_types.id = tasks.task_type_id')
            ->where('tasks.weekly_content_id', $weekId);

        /* فلتر بالتخصص */
        if ($specId) {
            $builder->join('task_specialties', 'task_specialties.task_id = tasks.id')
                    ->where('task_specialties.specialty_id', $specId);
        }

        /* بحث بالعنوان */
        if ($qTitle !== '') {
            $builder->like('tasks.title', $qTitle, 'both');
        }

        /* بحث باسم التخصص */
        if ($qSpec !== '') {
            $builder->join('task_specialties TS', 'TS.task_id = tasks.id')
                    ->join('specialties S', 'S.id = TS.specialty_id')
                    ->like('S.name', $qSpec, 'both');
        }

        /* فلترة بالتاريخ */
        if ($dateFrom && $dateTo) {
            $builder->where('tasks.visible_from_date >=', $dateFrom)
                    ->where('tasks.visible_from_date <=', $dateTo);
        } elseif ($dateFrom) {
            $builder->where('tasks.visible_from_date >=', $dateFrom);
        } elseif ($dateTo) {
            $builder->where('tasks.visible_from_date <=', $dateTo);
        }

        /* إجمالى النتائج */
        $total = (clone $builder)->countAllResults(false);

        /* الصفحة المطلوبة */
        $rows = $builder->orderBy('tasks.id', 'DESC')
                        ->limit($limit, $offset)
                        ->get()
                        ->getResultArray();

        /* إلحاق المرفقات والتخصصات */
        foreach ($rows as &$t) {
            $t['attachments_count'] = $this->taskFileModel
                ->where('task_id', $t['id'])
                ->countAllResults();

            $t['specialties'] = $t['is_for_all_specialties']
                ? ['عام']
                : array_column(
                    $this->taskSpecModel
                        ->select('specialties.name')
                        ->join('specialties', 'specialties.id = task_specialties.specialty_id')
                        ->where('task_specialties.task_id', $t['id'])
                        ->findAll(),
                    'name'
                );
        }

        return $this->json([
            'tasks' => $rows,
            'total' => $total,
        ]);
    }

    /* ====================================================================
     *                 جلب مهمة واحدة (عرض / تعديل)
     * ===================================================================*/
    public function getTask(int $id): ResponseInterface
    {
        try {
            $task = $this->taskModel
                ->select([
                    'tasks.id                     AS id',
                    'tasks.title                  AS title',
                    'tasks.description            AS description',
                    'tasks.visible_from_date',
                    'tasks.due_date',
                    'tasks.is_for_all_specialties',
                    'tasks.task_type_id',

                    'weekly_contents.week_number  AS week_number',
                    'courses.name                 AS course',
                    'universities.name            AS university',
                    'semesters.name               AS semester',
                    'academic_years.label         AS academic_year',
                ])
                ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id',            'left')
                ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id', 'left')
                ->join('courses',          'courses.id          = course_offerings.course_id',         'left')
                ->join('universities',     'universities.id     = course_offerings.university_id',     'left')
                ->join('semesters',        'semesters.id        = course_offerings.semester_id',       'left')
                ->join('academic_years',   'academic_years.id   = semesters.academic_year_id',         'left')
                ->where('tasks.id', $id)
                ->first();

            if (! $task) {
                return $this->response->setStatusCode(404)
                                      ->setJSON(['message' => 'المهمة غير موجودة']);
            }

            /* التخصصات */
            $task['specialty_ids'] = array_column(
                $this->taskSpecModel->select('specialty_id')
                                    ->where('task_id', $id)
                                    ->findAll(),
                'specialty_id'
            );

            /* الملفات */
            $task['files'] = $this->taskFileModel
                ->select('files.id AS file_id,
                          files.file_name,
                          files.file_path,
                          files.description, files.is_premium')
                ->join('files', 'files.id = task_files.file_id')
                ->where('task_files.task_id', $id)
                ->findAll();

            return $this->json($task);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'getTask error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
            );

            return $this->response->setStatusCode(500)
                                  ->setJSON(['message' => 'خطأ داخلي فى الخادم']);
        }
    }

    public function getTaskForView(int $id): ResponseInterface
    {
        // 1) جلب بيانات المهمة الأساسية مع الجامعة، المساق، الأسبوع، نوع المهمة
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
                'courses.name                    AS course',
                'universities.id                 AS university_id',
                'universities.name               AS university',
                'semesters.name                  AS semester',
                'academic_years.label            AS academic_year',
            ])
            ->join('task_types',       'task_types.id       = tasks.task_type_id')
            ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id')
            ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
            ->join('courses',          'courses.id          = course_offerings.course_id')
            ->join('universities',     'universities.id     = course_offerings.university_id')
            ->join('semesters',        'semesters.id        = course_offerings.semester_id')
            ->join('academic_years',   'academic_years.id   = semesters.academic_year_id')
            ->where('tasks.id', $id)
            ->first();
    
        if (! $task) {
            return $this->response
                        ->setStatusCode(404)
                        ->setJSON(['message' => 'المهمة غير موجودة']);
        }
    
        // 2) جلب التخصصات المرتبطة (أسماء فقط)
        $specialties = $this->taskSpecModel
            ->select('specialties.id, specialties.name')
            ->join('specialties', 'specialties.id = task_specialties.specialty_id')
            ->where('task_specialties.task_id', $id)
            ->findAll();
    
        // 3) جلب الملفات المرتبطة مع بياناتها
        $files = $this->taskFileModel
            ->select([
                'files.id        AS file_id',
                'files.file_name',
                'files.file_path',
                'files.description',
                'files.is_premium',
            ])
            ->join('files', 'files.id = task_files.file_id')
            ->where('task_files.task_id', $id)
            ->findAll();
    
        // 4) إعادة كل شيء في JSON واحد
        return $this->response->setJSON([
            'task'         => $task,
            'specialties'  => array_map(fn($s) => $s['name'], $specialties),
            'specialty_ids'=> array_map(fn($s) => $s['id'],   $specialties),
            'files'        => $files,
        ]);
}
    
    public function download(int $fileId): ResponseInterface
    {
        // جلب بيانات الملف من جدول الملفات
        $file = $this->fileModel->find($fileId);
    
        if (! $file) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود');
        }
    
        // تحقق من وجود الملف فعليًا في المسار
        $filePath = ROOTPATH . 'public' . $file['file_path'];  // لاحظ التصحيح هنا
    
        if (! is_file($filePath)) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود على الخادم');
        }
    
        // إرسال الملف باسم واضح
    $mimeType = mime_content_type($filePath);
    
    return $this->response
        ->setHeader('Content-Type', $mimeType)
        ->setHeader('Content-Disposition', 'inline; filename="' . $file['file_name'] . '"')
        ->setBody(file_get_contents($filePath));
    
    }

    /* ====================================================================
     *                    إنشاء أو تعديل مهمة
     * ===================================================================*/
/*    public function createOrUpdateTask(): ResponseInterface
    {
        $post = $this->request->getPost();

        $data = [
            'weekly_content_id'      => $post['weekId'],
            'task_type_id'           => $post['taskType'],
            'title'                  => $post['taskTitle']       ?? '',
            'description'            => $post['taskDescription'] ?? '',
            'is_for_all_specialties' => in_array(
                'all',
                json_decode($post['specialties'] ?? '[]', true),
                true
            ) ? 1 : 0,
            'visible_from_date'      => $post['publishDate'] ?? null,
            'due_date'               => $post['dueDate']     ?? null,
        ];

        // حفظ أو تعديل 
        if (! empty($post['taskId'])) {
            $taskId = (int) $post['taskId'];
            $this->taskModel->update($taskId, $data);
        } else {
            $taskId = $this->taskModel->insert($data);
        }

        // التخصصات 
        $this->taskSpecModel->where('task_id', $taskId)->delete();

        $specs = json_decode($post['specialties'] ?? '[]', true) ?: [];
        foreach ($specs as $sid) {
            if ($sid !== 'all') {
                $this->taskSpecModel->insert([
                    'task_id'      => $taskId,
                    'specialty_id' => (int) $sid,
                ]);
            }
        }

        // حذف ملفات 
        $removed = json_decode($post['removedFiles'] ?? '[]', true) ?: [];
        foreach ($removed as $fid) {
            $rec = $this->fileModel->find($fid);
            if (! $rec) {
                continue;
            }

            @unlink($this->absPath($rec['file_path']));

            $this->taskFileModel
                ->where(['task_id' => $taskId, 'file_id' => $fid])
                ->delete();
            $this->fileModel->delete($fid);
        }

        // تحديث أوصاف الملفات الموجودة/
        $existing = json_decode($post['existingFiles'] ?? '[]', true) ?: [];
        foreach ($existing as $e) {
            $this->fileModel->update(
                (int) ($e['file_id'] ?? 0),
                ['description' => $e['description'] ?? '']
            );
        }
            // جلب أوصاف الملفات المُرسلة مع الطلب
        $filesDesc = $this->request->getPost('filesDesc') ?? [];
        
        // @var UploadedFile[] $files //
        $files = $this->request->getFileMultiple('files') ?? [];
        
        // ⬅️ لاحظ إضافة $idx في رأس الحلقة
        foreach ($files as $idx => $file) {
            if (! $file->isValid() || $file->hasMoved()) {
                continue;
            }
        
            $newName = $file->getRandomName();
            $file->move($this->absUpload, $newName);
        
            $relPath = $this->relUpload . $newName;
        
            $fileId = $this->fileModel->insert([
                'file_name'       => $file->getClientName(),
                'file_path'       => $relPath,
                'mime_type'       => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'uploader_id'     => session()->get('user_id'),
                // ⬅️ استخدام الفهرس المطابق
                'description'     => $filesDesc[$idx] ?? '',
            ]);
        
            $this->taskFileModel->insert([
                'task_id' => $taskId,
                'file_id' => $fileId,
            ]);
        }
        return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'status' => 'success',
                ]);
}
*/
public function createOrUpdateTask(): ResponseInterface
{
    $post = $this->request->getPost();

    $data = [
        'weekly_content_id'      => $post['weekId'],
        'task_type_id'           => $post['taskType'],
        'title'                  => $post['taskTitle']       ?? '',
        'description'            => $post['taskDescription'] ?? '',
        'is_for_all_specialties' => in_array(
            'all',
            json_decode($post['specialties'] ?? '[]', true),
            true
        ) ? 1 : 0,
        'visible_from_date'      => $post['publishDate'] ?? null,
        'due_date'               => $post['dueDate']     ?? null,
    ];

    // حفظ أو تعديل
    if (! empty($post['taskId'])) {
        $taskId = (int) $post['taskId'];
        $this->taskModel->update($taskId, $data);
    } else {
        $taskId = $this->taskModel->insert($data);
    }

    // التخصصات
    $this->taskSpecModel->where('task_id', $taskId)->delete();

    $specs = json_decode($post['specialties'] ?? '[]', true) ?: [];
    foreach ($specs as $sid) {
        if ($sid !== 'all') {
            $this->taskSpecModel->insert([
                'task_id'      => $taskId,
                'specialty_id' => (int) $sid,
            ]);
        }
    }

    // حذف الملفات
    $removed = json_decode($post['removedFiles'] ?? '[]', true) ?: [];
    foreach ($removed as $fid) {
        $rec = $this->fileModel->find($fid);
        if (! $rec) {
            continue;
        }

        @unlink($this->absPath($rec['file_path']));

        $this->taskFileModel
            ->where(['task_id' => $taskId, 'file_id' => $fid])
            ->delete();
        $this->fileModel->delete($fid);
    }

    // تحديث أوصاف وخصائص الملفات الموجودة
    $existing = json_decode($post['existingFiles'] ?? '[]', true) ?: [];
    foreach ($existing as $e) {
        $this->fileModel->update(
            (int) ($e['file_id'] ?? 0),
            [
                'description' => $e['description'] ?? '',
                'is_premium'  => $e['is_premium'] ?? 0,
            ]
        );
    }

    // الملفات الجديدة
    $filesDesc    = $this->request->getPost('filesDesc')     ?? [];
    $filesPremium = $this->request->getPost('filesPremium')  ?? [];
    $files        = $this->request->getFileMultiple('files') ?? [];

    foreach ($files as $idx => $file) {
        if (! $file->isValid() || $file->hasMoved()) {
            continue;
        }

        $newName = $file->getRandomName();
        $file->move($this->absUpload, $newName);
        $relPath = $this->relUpload . $newName;

        $fileId = $this->fileModel->insert([
            'file_name'       => $file->getClientName(),
            'file_path'       => $relPath,
            'mime_type'       => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'uploader_id'     => session()->get('user_id'),
            'description'     => $filesDesc[$idx]    ?? '',
            'is_premium'      => $filesPremium[$idx] ?? 0,
        ]);

        $this->taskFileModel->insert([
            'task_id' => $taskId,
            'file_id' => $fileId,
        ]);
    }

    return $this->response
        ->setStatusCode(200)
        ->setJSON([
            'status' => 'success',
        ]);
}

    /* ====================================================================
     *                           حذف مهمة
     * ===================================================================*/
    public function deleteTask(int $id): ResponseInterface
    {
        $this->taskModel->delete($id);
        return $this->json(['status' => 'deleted']);
    }

    /* ====================================================================
     *                           دوال مساعدة
     * ===================================================================*/
    private function absPath(string $dbPath): string
    {
        return str_starts_with($dbPath, '/uploads')
            ? rtrim(FCPATH, '/') . $dbPath
            : rtrim(WRITEPATH, '/') . '/uploads/' . ltrim($dbPath, '/');
    }

    private function json($data): ResponseInterface
    {
        return $this->response->setJSON($data);
    }
}
