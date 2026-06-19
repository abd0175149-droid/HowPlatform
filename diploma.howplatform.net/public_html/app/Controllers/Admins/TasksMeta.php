<?php

declare(strict_types=1);

namespace App\Controllers\Admins;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\FileModel;
use App\Models\SemesterModel;
use App\Models\SpecialtyModel;
use App\Models\TaskFileModel;
use App\Models\TaskModel;
use App\Models\TaskSpecialtyModel;
use App\Models\TaskTypeModel;
use App\Models\UniversityAcademicYearModel;
use App\Models\UniversitySpecialtyModel;
use App\Models\WeeklyContentModel;
use App\Libraries\FcmLibrary;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

class TasksMeta extends BaseController
{
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

    /** مسار نسبي تحت الجذر العام للموقع */
    private string $relUpload = 'uploads/tasks/';
    /** المسار الفيزيائي المفضّل (داخل public/) */
    private string $absUpload;

    public function __construct()
    {
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

        // نجعل الرفع إلى public/uploads/tasks/ بشكل افتراضي
        $this->absUpload = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->relUpload;

        if (! is_dir($this->absUpload)) {
            @mkdir($this->absUpload, 0775, true);
        }
    }

    /* ============================ Helpers ============================ */

    private function json($data): ResponseInterface
    {
        return $this->response->setJSON($data);
    }

    /**
     * يحلّ معرّف المستخدم من السِّشن لكتابته في files.uploader_id
     * يُعيد null عند الفشل (FK يسمح بـ NULL).
     */
    private function getDiplomaUserIdFromSession(): ?int
    {
        $s = session();
        $uid = null;

        if ($s->has('user_id')) {
            $uid = (int) $s->get('user_id'); // المفتاح الذي زوّدتني به
        } elseif ($s->has('id')) {
            $uid = (int) $s->get('id');
        } else {
            $auth = $s->get('auth_user');
            if (is_array($auth) && isset($auth['id'])) {
                $uid = (int) $auth['id'];
            }
        }

        if (! $uid || $uid < 1) {
            log_message('error', '[TASKS] uploader_id not resolved from session; using NULL.');
            return null;
        }

        return $uid;
    }

    /** الامتدادات/الأنماط التي نسمح لها بـ allow_public_view */
    private function isAudioOrVideo(?string $mime, ?string $fileName): bool
    {
        $mime = strtolower((string) $mime);
        if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) return true;

        $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        return in_array($ext, [
            'mp3','wav','m4a','aac','ogg','opus','flac',
            'mp4','m4v','mov','mkv','webm','avi',
        ], true);
    }

    /**
     * يحاول إيجاد المسار الفيزيائي للملف المخزّن في DB (file_path) مثل: /uploads/tasks/xxxx.ext
     * يدعم أكثر من احتمال لمسار الجذر (بعض الاستضافات).
     */
    private function resolveAbsoluteFromDbPath(string $dbPath): ?string
    {
        $rel = ltrim($dbPath, '/\\'); // uploads/tasks/xxx
        $candidates = [];

        // 1) تحت public/ (نمط CI4 القياسي) — حيث قمنا بالرفع
        $candidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $rel;

        // 2) مستوى أعلى من public (بعض الاستضافات تضع الملفات تحت public_html مباشرة)
        $candidates[] = rtrim(\dirname(rtrim(FCPATH, '/\\')), '/\\') . DIRECTORY_SEPARATOR . $rel;

        // 3) لو dbPath كان مطلق ويبدأ بـ /uploads/… وفعليًا موجود
        $absFromRoot = DIRECTORY_SEPARATOR . $rel;
        if (is_file($absFromRoot)) {
            $candidates[] = $absFromRoot;
        }

        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }

        log_message('error', '[TASKS] resolveAbsoluteFromDbPath failed for: ' . $dbPath . ' | tried: ' . implode(' | ', $candidates));
        return null;
    }

    private function deletePhysicalByDbPath(string $dbPath): bool
    {
        $abs = $this->resolveAbsoluteFromDbPath($dbPath);
        return $abs ? @unlink($abs) : false;
    }

    /* ============================ Basics ============================ */

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

    public function getAiJobTypes(): ResponseInterface
    {
        $db = \Config\Database::connect();
        $types = $db->table('ai_job_types')->select('type_key, title')->where('is_enabled', 1)->get()->getResultArray();
        return $this->json($types);
    }

    public function getSpecialties($univId = null): ResponseInterface
    {
        if ($univId) {
            $rows = $this->uniSpecModel->where('university_id', (int)$univId)->findAll();
            $ids  = array_column($rows, 'specialty_id');
            $specs = empty($ids) ? [] :
                $this->specialtyModel->whereIn('id', $ids)->orderBy('name','ASC')->findAll();
        } else {
            $specs = $this->specialtyModel->orderBy('name','ASC')->findAll();
        }
        return $this->json($specs);
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
        $univId = (int) ($this->request->getGet('univId') ?? 0);
        $semId  = (int) ($this->request->getGet('semId')  ?? 0);

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
            $this->weeklyModel->where('course_offering_id', (int)$offeringId)->findAll()
        );
    }

    /* ============================ Listing ============================ */

    public function getTasks(): ResponseInterface
    {
        $weekId   = (int) ($this->request->getGet('weekId') ?? 0);
        $specId   = (int) ($this->request->getGet('specId') ?? 0);
        $qTitle   = trim((string) ($this->request->getGet('qTitle') ?? ''));
        $qSpec    = trim((string) ($this->request->getGet('qSpec')  ?? ''));
        $dateFrom = $this->request->getGet('dateFrom');
        $dateTo   = $this->request->getGet('dateTo');

        $page   = max(1, (int) ($this->request->getGet('page')  ?? 1));
        $limit  = max(1, (int) ($this->request->getGet('limit') ?? 10));
        $offset = ($page - 1) * $limit;

        $builder = $this->taskModel->select([
                'tasks.id                    AS id',
                'weekly_contents.week_number AS week_number',
                'courses.name                AS course',
                'universities.name           AS university',
                'task_types.name             AS task_type',
                'tasks.title                 AS title',
                'tasks.description           AS description',
                'tasks.visible_from_date',
                'tasks.due_date',
                'tasks.is_for_all_specialties',
                'tasks.task_type_id',
                'tasks.ai_tool_version',
            ])
            ->join('weekly_contents',  'weekly_contents.id = tasks.weekly_content_id')
            ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
            ->join('universities',     'universities.id = course_offerings.university_id')
            ->join('courses',          'courses.id = course_offerings.course_id')
            ->join('semesters',        'semesters.id = course_offerings.semester_id')
            ->join('academic_years',   'academic_years.id = semesters.academic_year_id')
            ->join('task_types',       'task_types.id = tasks.task_type_id')
            ->where('tasks.weekly_content_id', $weekId);

        if ($specId > 0) {
            $builder->join('task_specialties', 'task_specialties.task_id = tasks.id')
                    ->where('task_specialties.specialty_id', $specId);
        }
        if ($qTitle !== '') {
            $builder->like('tasks.title', $qTitle, 'both');
        }
        if ($qSpec !== '') {
            $builder->join('task_specialties TS', 'TS.task_id = tasks.id')
                    ->join('specialties S', 'S.id = TS.specialty_id')
                    ->like('S.name', $qSpec, 'both');
        }
        if ($dateFrom && $dateTo) {
            $builder->where('tasks.visible_from_date >=', $dateFrom)
                    ->where('tasks.visible_from_date <=', $dateTo);
        } elseif ($dateFrom) {
            $builder->where('tasks.visible_from_date >=', $dateFrom);
        } elseif ($dateTo) {
            $builder->where('tasks.visible_from_date <=', $dateTo);
        }

        $total = (clone $builder)->countAllResults(false);

        $rows = $builder->orderBy('tasks.id', 'DESC')
                        ->limit($limit, $offset)
                        ->get()
                        ->getResultArray();

        foreach ($rows as &$t) {
            $t['attachments_count'] = $this->taskFileModel
                ->where('task_id', (int)$t['id'])
                ->countAllResults();

            $t['specialties'] = ((int)$t['is_for_all_specialties'] === 1)
                ? ['عام']
                : array_column(
                    $this->taskSpecModel->select('specialties.name')
                        ->join('specialties', 'specialties.id = task_specialties.specialty_id')
                        ->where('task_specialties.task_id', (int)$t['id'])
                        ->findAll(),
                    'name'
                );
        }

        return $this->json(['tasks' => $rows, 'total' => $total]);
    }

    /* ============================ Single ============================ */

    public function getTask(int $id): ResponseInterface
    {
        try {
            $task = $this->taskModel
                ->select([
                    'tasks.id                    AS id',
                    'tasks.title                 AS title',
                    'tasks.description           AS description',
                    'tasks.task_prompt           AS task_prompt',
                    'tasks.visible_from_date',
                    'tasks.due_date',
                    'tasks.is_for_all_specialties',
                    'tasks.task_type_id',
                    'tasks.ai_tool_version',
                    'weekly_contents.week_number AS week_number',
                    'courses.name                AS course',
                    'universities.name           AS university',
                    'semesters.name              AS semester',
                    'academic_years.label        AS academic_year',
                ])
                ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id',            'left')
                ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id', 'left')
                ->join('courses',          'courses.id          = course_offerings.course_id',         'left')
                ->join('universities',     'universities.id     = course_offerings.university_id',     'left')
                ->join('semesters',        'semesters.id        = course_offerings.semester_id',       'left')
                ->join('academic_years',   'academic_years.id   = semesters.academic_year_id',         'left')
                ->where('tasks.id', (int)$id)
                ->first();

            if (! $task) {
                return $this->response->setStatusCode(404)->setJSON(['message' => 'المهمة غير موجودة']);
            }

            $task['specialty_ids'] = array_column(
                $this->taskSpecModel->select('specialty_id')->where('task_id', (int)$id)->findAll(),
                'specialty_id'
            );

            $task['files'] = $this->taskFileModel
                ->select('files.id AS file_id, files.file_name, files.file_path, files.description, files.is_premium, files.allow_public_view')
                ->join('files', 'files.id = task_files.file_id')
                ->where('task_files.task_id', (int)$id)
                ->findAll();

            return $this->json($task);
        } catch (\Throwable $e) {
            log_message('error', 'getTask error: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            return $this->response->setStatusCode(500)->setJSON(['message' => 'خطأ داخلي فى الخادم']);
        }
    }

    public function getTaskForView(int $id): ResponseInterface
    {
        $task = $this->taskModel
            ->select([
                'tasks.id                    AS id',
                'tasks.title                 AS title',
                'tasks.description           AS description',
                'tasks.visible_from_date',
                'tasks.due_date',
                'tasks.is_for_all_specialties',
                'tasks.ai_tool_version',
                'task_types.name             AS task_type',
                'weekly_contents.week_number AS week_number',
                'courses.name                AS course',
                'universities.id             AS university_id',
                'universities.name           AS university',
                'semesters.name              AS semester',
                'academic_years.label        AS academic_year',
            ])
            ->join('task_types',       'task_types.id       = tasks.task_type_id')
            ->join('weekly_contents',  'weekly_contents.id  = tasks.weekly_content_id')
            ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
            ->join('courses',          'courses.id          = course_offerings.course_id')
            ->join('universities',     'universities.id     = course_offerings.university_id')
            ->join('semesters',        'semesters.id        = course_offerings.semester_id')
            ->join('academic_years',   'academic_years.id   = semesters.academic_year_id')
            ->where('tasks.id', (int)$id)
            ->first();

        if (! $task) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'المهمة غير موجودة']);
        }

        $specialties = $this->taskSpecModel
            ->select('specialties.id, specialties.name')
            ->join('specialties', 'specialties.id = task_specialties.specialty_id')
            ->where('task_specialties.task_id', (int)$id)
            ->findAll();

        $files = $this->taskFileModel
            ->select('files.id AS file_id, files.file_name, files.file_path, files.description, files.is_premium, files.allow_public_view')
            ->join('files', 'files.id = task_files.file_id')
            ->where('task_files.task_id', (int)$id)
            ->findAll();

        return $this->response->setJSON([
            'task'          => $task,
            'specialties'   => array_map(static fn($s) => $s['name'], $specialties),
            'specialty_ids' => array_map(static fn($s) => $s['id'],   $specialties),
            'files'         => $files,
        ]);
    }

    public function download(int $fileId): ResponseInterface
    {
        $file = $this->fileModel->find((int)$fileId);
        if (! $file) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود في قاعدة البيانات');
        }

        $abs = $this->resolveAbsoluteFromDbPath((string)$file['file_path']);
        if (! $abs) {
            return $this->response->setStatusCode(404)->setBody('الملف غير موجود على الخادم');
        }

        $mime = mime_content_type($abs) ?: ($file['mime_type'] ?? 'application/octet-stream');
        $name = $file['file_name'] ?? basename($abs);

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . $name . '"')
            ->setBody((string) file_get_contents($abs));
    }

    /* ============================ Create/Update / Clone / Move ============================ */

    public function createOrUpdateTask(): ResponseInterface
    {
        log_message('error', '=== [TASKS] createOrUpdateTask START ===');
        log_message('error', '[TASKS] CT=' . ($this->request->getHeaderLine('Content-Type') ?? ''));
        log_message('error', '[TASKS] CONTENT_LENGTH=' . ($_SERVER['CONTENT_LENGTH'] ?? ''));
        log_message('error', '[TASKS] upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size'));
        log_message('error', '[TASKS] _FILES snapshot: ' . print_r($_FILES, true));

        // تحقق من تخطي post_max_size
        $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (empty($_POST) && empty($_FILES) && $contentLen > 0) {
            return $this->response->setStatusCode(413)->setJSON([
                'status'  => 'error',
                'message' => 'حجم الطلب تجاوز post_max_size.',
            ]);
        }

        // تأكد من صلاحية مجلد الرفع
        if (! is_dir($this->absUpload)) @mkdir($this->absUpload, 0775, true);
        if (! is_writable($this->absUpload)) {
            log_message('error', '[TASKS] Upload dir not writable: ' . $this->absUpload);
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Upload directory is not writable',
            ]);
        }

        $post          = $this->request->getPost();
        $expectFiles   = (string)($post['expectFiles'] ?? '0') === '1';
        $actionMode    = (string)($post['actionMode'] ?? 'update'); // update|clone|move
        $sourceTaskId  = (int)($post['sourceTaskId'] ?? 0);

        // مصفوفة اختيارية لربط ملفات موجودة بالمهمة الجديدة (clone/move)
        $linkExisting  = $post['linkExistingFiles'] ?? '[]';
        $linkExisting  = is_array($linkExisting) ? $linkExisting : json_decode((string)$linkExisting, true);
        $linkExisting  = array_values(array_filter(array_map('intval', $linkExisting ?? [])));

        // التحقق من نوع المهمة (حقل إلزامي)
        $taskType = ($post['taskType'] ?? '') !== '' ? (int) $post['taskType'] : null;
        if (empty($taskType) || $taskType <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'نوع المهمة حقل إلزامي',
            ]);
        }

        // التحقق من وجود نوع المهمة في قاعدة البيانات
        $taskTypeExists = $this->taskTypeModel->find($taskType);
        if (!$taskTypeExists) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'نوع المهمة المحدد غير موجود',
            ]);
        }

        // بيانات المهمة
        $data = [
            'weekly_content_id'      => (int) ($post['weekId'] ?? 0),
            'task_type_id'           => $taskType,
            'title'                  => (string) ($post['taskTitle']       ?? ''),
            'description'            => (string) ($post['taskDescription'] ?? ''),
            'task_prompt'            => (string) ($post['taskPrompt']     ?? ''),
            'ai_tool_version'        => (!empty($post['aiToolVersion'])) ? (string)$post['aiToolVersion'] : null,
            'is_for_all_specialties' => in_array('all', json_decode((string)($post['specialties'] ?? '[]'), true) ?: [], true) ? 1 : 0,
            'visible_from_date'      => $post['publishDate'] ?? null,
            'due_date'               => $post['dueDate']     ?? null,
        ];

        // حفظ/تعديل
        $taskId = 0;
        if (! empty($post['taskId'])) {
            $taskId = (int) $post['taskId'];
            $this->taskModel->update($taskId, $data);
        } else {
            $taskId = (int) $this->taskModel->insert($data);
        }
        if ($taskId <= 0) {
            return $this->response->setStatusCode(500)->setJSON(['status'=>'error','message'=>'Task save failed']);
        }

        // التخصصات
        $this->taskSpecModel->where('task_id', $taskId)->delete();
        $specs = json_decode((string) ($post['specialties'] ?? '[]'), true) ?: [];
        foreach ($specs as $sid) {
            if ($sid !== 'all') {
                $this->taskSpecModel->insert([
                    'task_id'      => $taskId,
                    'specialty_id' => (int) $sid,
                ]);
            }
        }

        // حذف روابط ملفات أزيلت من الواجهة (في التعديل فقط)
        $removed = json_decode((string) ($post['removedFiles'] ?? '[]'), true) ?: [];
        foreach ($removed as $fid) {
            $fid = (int) $fid;
            if ($fid <= 0) continue;
            $this->taskFileModel->where(['task_id'=>$taskId,'file_id'=>$fid])->delete();
            $stillLinked = $this->taskFileModel->where('file_id',$fid)->countAllResults() > 0;
            if (! $stillLinked) {
                $rec = $this->fileModel->find($fid);
                if ($rec) {
                    $this->deletePhysicalByDbPath((string)$rec['file_path']);
                    $this->fileModel->delete($fid);
                }
            }
        }

        // تحديث خصائص ملفات موجودة (مسموح فقط في وضع update)
        $existingPayload = json_decode((string) ($post['existingFiles'] ?? '[]'), true) ?: [];
        if ($actionMode === 'update') {
            foreach ($existingPayload as $e) {
                $fileId  = (int) ($e['file_id'] ?? 0);
                if ($fileId <= 0) continue;
                $desc    = (string) ($e['description'] ?? '');
                $isPrem  = ! empty($e['is_premium']) ? 1 : 0;
                $wantPub = ! empty($e['allow_public_view']) ? 1 : 0;
                $rec     = $this->fileModel->find($fileId) ?? [];
                $allow   = $wantPub && $this->isAudioOrVideo($rec['mime_type'] ?? null, $rec['file_name'] ?? null) ? 1 : 0;

                $this->fileModel->update($fileId, [
                    'description'       => $desc,
                    'is_premium'        => $isPrem,
                    'allow_public_view' => $allow,
                ]);
            }
        }

        // ============ ملفات جديدة ============ //
        $filesDesc    = $this->request->getPost('filesDesc')    ?? [];
        $filesPremium = $this->request->getPost('filesPremium') ?? [];
        $filesPublic  = $this->request->getPost('filesPublic')  ?? [];

        // اجمع كل الملفات من أي مفتاح
        $allFilesArr = $this->request->getFiles();
        $flatFiles   = [];
        $flatten = function($node) use (&$flatten, &$flatFiles) {
            if ($node instanceof UploadedFile) { $flatFiles[] = $node; return; }
            if (is_array($node)) { foreach ($node as $n) $flatten($n); }
        };
        $flatten($allFilesArr);

        log_message('error', '[TASKS] collected uploaded files count=' . count($flatFiles));
        $uploaderId = $this->getDiplomaUserIdFromSession();

        $processedNew = 0;
        foreach ($flatFiles as $idx => $file) {
            if (! $file->isValid()) {
                log_message('error', "[TASKS] File[$idx] invalid: code={$file->getError()}, text={$file->getErrorString()}");
                continue;
            }
            if ($file->hasMoved()) continue;

            $newName = $file->getRandomName();
            try { $file->move($this->absUpload, $newName); }
            catch (\Throwable $ex) { log_message('error', "[TASKS] move() exception: " . $ex->getMessage()); continue; }

            if (! $file->hasMoved()) {
                log_message('error', "[TASKS] move() did not mark file as moved! idx=$idx");
                continue;
            }

            $dbRelPath = '/' . ltrim($this->relUpload, '/\\') . $newName; // مثال: /uploads/tasks/xxxx.ext
            $mime      = (string) $file->getClientMimeType();
            $sizeBytes = (int) $file->getSize();
            $origName  = (string) $file->getClientName();

            $desc     = (string) ($filesDesc[$idx]    ?? '');
            $isPrem   = ! empty($filesPremium[$idx]) ? 1 : 0;
            $wantPub  = ! empty($filesPublic[$idx])  ? 1 : 0;
            $allowPub = $wantPub && $this->isAudioOrVideo($mime, $origName) ? 1 : 0;

            try {
                $fileId = (int) $this->fileModel->insert([
                    'file_name'         => $origName,
                    'file_path'         => $dbRelPath,
                    'mime_type'         => $mime,
                    'file_size_bytes'   => $sizeBytes,
                    'uploader_id'       => $uploaderId,   // قد تكون NULL (FK يسمح NULL)
                    'description'       => $desc,
                    'is_premium'        => $isPrem,
                    'allow_public_view' => $allowPub,
                ], true);
            } catch (\Throwable $dbEx) {
                log_message('error', '[TASKS] fileModel->insert exception: ' . $dbEx->getMessage());
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => 'error',
                    'message' => 'DB insert failed for files',
                    'db_error'=> $dbEx->getMessage(),
                ]);
            }

            if ($fileId > 0) {
                $this->taskFileModel->insert(['task_id' => $taskId, 'file_id' => $fileId]);
                $processedNew++;
            } else {
                log_message('error', '[TASKS] fileModel->insert failed: ' . print_r($this->fileModel->errors(), true));
            }
        }

        // ============ ربط ملفات موجودة مع مهمة جديدة (clone/move) ============ //
        $linkedCount = 0;
        if (in_array($actionMode, ['clone', 'move'], true) && !empty($linkExisting)) {
            // اصنع خريطة خصائص مطلوبة من existingPayload (حسب file_id)
            $desiredMap = [];
            foreach ($existingPayload as $e) {
                $fid = (int)($e['file_id'] ?? 0);
                if ($fid > 0) $desiredMap[$fid] = [
                    'description'       => (string)($e['description'] ?? ''),
                    'is_premium'        => !empty($e['is_premium']) ? 1 : 0,
                    'allow_public_view' => !empty($e['allow_public_view']) ? 1 : 0,
                ];
            }

            foreach ($linkExisting as $fid) {
                $fid = (int)$fid; if ($fid <= 0) continue;
                $rec = $this->fileModel->find($fid);
                if (! $rec) continue;

                $targetFileId = $fid; // الافتراضي: ربط نفس الصف
                if (isset($desiredMap[$fid])) {
                    $want = $desiredMap[$fid];
                    $allowPub = $want['allow_public_view'] && $this->isAudioOrVideo($rec['mime_type'] ?? null, $rec['file_name'] ?? null) ? 1 : 0;

                    $needsDup =
                        (string)$rec['description']       !== (string)$want['description'] ||
                        (int)   $rec['is_premium']        !== (int)   $want['is_premium'] ||
                        (int)   $rec['allow_public_view'] !== (int)   $allowPub;

                    if ($needsDup) {
                        $dupId = (int)$this->fileModel->insert([
                            'file_name'         => $rec['file_name'],
                            'file_path'         => $rec['file_path'],   // نفس الفيزيائي
                            'mime_type'         => $rec['mime_type'],
                            'file_size_bytes'   => $rec['file_size_bytes'] ?? 0,
                            'uploader_id'       => $rec['uploader_id'] ?? $this->getDiplomaUserIdFromSession(),
                            'description'       => $want['description'],
                            'is_premium'        => (int)$want['is_premium'],
                            'allow_public_view' => (int)$allowPub,
                        ], true);
                        if ($dupId > 0) $targetFileId = $dupId;
                    }
                }

                $this->taskFileModel->insert(['task_id' => $taskId, 'file_id' => $targetFileId]);
                $linkedCount++;
            }
        }

        // 422 فقط إذا الواجهة توقعت ملفات جديدة ولم يُعالج أي ملف جديد
        if ($expectFiles && $processedNew === 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'تم إرسال حقول ملفات لكن لم يتمكن الخادم من معالجتها.',
                'debug'   => [
                    '_FILES'              => $_FILES,
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size'       => ini_get('post_max_size'),
                ],
            ]);
        }

        // ============ إرسال إشعارات FCM عند إنشاء مهمة جديدة ============ //
        if (empty($post['taskId'])) { // مهمة جديدة فقط
            try {
                $this->sendTaskNotification($taskId, $data['title']);
            } catch (\Throwable $notifEx) {
                log_message('error', '[TASKS] Failed to send notification: ' . $notifEx->getMessage());
                // لا نوقف العملية إذا فشل الإشعار
            }
        }

        log_message('error', "[TASKS] DONE: task_id=$taskId, new_files_processed=$processedNew, linked_existing=$linkedCount, mode=$actionMode");
        return $this->response->setStatusCode(200)->setJSON([
            'status'                 => 'success',
            'task_id'                => $taskId,
            'processed_files_count'  => $processedNew,
            'linked_existing_count'  => $linkedCount,
            'action_mode'            => $actionMode,
        ]);
    }

    /**
     * إرسال إشعار FCM عند إضافة مهمة جديدة
     */
    private function sendTaskNotification(int $taskId, string $taskTitle): void
    {
        try {
            $fcm = new FcmLibrary();
            $db = \Config\Database::connect();

            // جلب معلومات المهمة
            $task = $db->table('tasks')
                ->select('tasks.*, weekly_contents.course_offering_id, course_offerings.university_id, semesters.academic_year_id, courses.name as course_name')
                ->join('weekly_contents', 'weekly_contents.id = tasks.weekly_content_id')
                ->join('course_offerings', 'course_offerings.id = weekly_contents.course_offering_id')
                ->join('semesters', 'semesters.id = course_offerings.semester_id')
                ->join('courses', 'courses.id = course_offerings.course_id')
                ->where('tasks.id', $taskId)
                ->get()
                ->getRowArray();

            if (!$task) {
                log_message('warning', "[TASKS] Task not found for notification: task_id=$taskId");
                return;
            }

            $universityId = (int) $task['university_id'];
            $academicYearId = (int) $task['academic_year_id'];
            $isForAllSpecialties = (int) ($task['is_for_all_specialties'] ?? 0);
            $courseName = $task['course_name'] ?? '';

            // جلب التخصصات المرتبطة بالمهمة
            $taskSpecialties = $db->table('task_specialties')
                ->where('task_id', $taskId)
                ->get()
                ->getResultArray();
            $specialtyIds = array_column($taskSpecialties, 'specialty_id');

            // جلب المستخدمين المؤهلين
            $query = $db->table('diploma_users as du')
                ->distinct()
                ->select('du.user_id, ud.push_token')
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
            $userIds = array_unique(array_column($devices, 'user_id'));

            if (empty($tokens)) {
                log_message('info', "[TASKS] No devices found for task notification: task_id=$taskId");
                return;
            }

            // إعداد نص الإشعار
            $title = 'مهمة جديدة';
            $body = !empty($courseName) 
                ? "تم إضافة مهمة جديدة في مساق: {$courseName}"
                : "تم إضافة مهمة جديدة: {$taskTitle}";

            // إرسال الإشعارات
            $result = $fcm->sendToMultipleDevices(
                $tokens,
                $title,
                $body,
                [
                    'type' => 'task',
                    'task_id' => $taskId,
                    'course_offering_id' => (int) $task['course_offering_id'],
                ]
            );

            log_message('info', "[TASKS] FCM notification sent", [
                'task_id' => $taskId,
                'sent' => $result['success'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);

            // حفظ الإشعارات في قاعدة البيانات
            $notifications = [];
            foreach ($userIds as $userId) {
                $notifications[] = [
                    'user_id' => (int) $userId,
                    'notification_type_id' => 1, // نوع: مهمة جديدة
                    'title' => $title,
                    'message' => $body,
                    'redirect_url' => '/tasks/' . $taskId,
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            if (!empty($notifications)) {
                $db->table('notifications')->insertBatch($notifications);
            }
        } catch (\Throwable $e) {
            log_message('error', '[TASKS] Exception in sendTaskNotification', [
                'task_id' => $taskId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /* ============================ Delete ============================ */

    public function deleteTask(int $id): ResponseInterface
    {
        try {
            // التحقق من وجود المهمة
            $task = $this->taskModel->find((int)$id);
            if (!$task) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'المهمة غير موجودة',
                ]);
            }

            // جمع روابط الملفات قبل الحذف
            $fileLinks = $this->taskFileModel->where('task_id', (int)$id)->findAll();

            // حذف روابط الملفات
            $this->taskFileModel->where('task_id', (int)$id)->delete();
            
            // حذف المهمة
            $deleted = $this->taskModel->delete((int)$id);
            
            if (!$deleted) {
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => 'error',
                    'message' => 'فشل حذف المهمة من قاعدة البيانات',
                ]);
            }

            // حذف الملفات غير المرتبطة بمهام أخرى
            foreach ($fileLinks as $link) {
                $fid = (int) $link['file_id'];
                $stillLinked = $this->taskFileModel->where('file_id', $fid)->countAllResults() > 0;

                if (! $stillLinked) {
                    $rec = $this->fileModel->find($fid);
                    if ($rec) {
                        $this->deletePhysicalByDbPath((string)$rec['file_path']);
                        $this->fileModel->delete($fid);
                    }
                }
            }

            return $this->response->setStatusCode(200)->setJSON([
                'status'  => 'deleted',
                'message' => 'تم حذف المهمة بنجاح',
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[TASKS] deleteTask error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء حذف المهمة',
            ]);
        }
    }
}
