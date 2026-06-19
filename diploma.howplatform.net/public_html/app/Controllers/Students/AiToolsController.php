<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Controllers\BaseController;
use App\Helpers\StudentViewHelper;
use App\Models\AiToolPlacementModel;
use App\Services\AiTools\AiToolAccessService;
use App\Services\AiTools\AiToolRegistry;
use App\Services\AiTools\AiToolService;
use CodeIgniter\API\ResponseTrait;

/**
 * AiToolsController: كنترولر موحد لأدوات الذكاء الاصطناعي للطلاب
 */
class AiToolsController extends BaseController
{
    use ResponseTrait;

    protected function userId(): ?int
    {
        // استخدام الإعدادات الفعالة للأدمن
        $uid = StudentViewHelper::getEffectiveUserId();
        if ($uid) {
            return $uid;
        }

        $uid = session('user_id');
        if (!$uid && function_exists('auth') && auth()?->user()) {
            $uid = auth()->user()->id ?? null;
        }
        return $uid ? (int) $uid : null;
    }

    /**
     * GET /students/ai-tools
     * قائمة الأدوات المتاحة
     */
    public function index()
    {
        $userId = $this->userId();
        $tools = AiToolRegistry::getEnabledTools();
        
        // تصفية الأدوات بناءً على صلاحيات المستخدم
        if ($userId) {
            $tools = array_filter($tools, function($t) use ($userId) {
                return $this->isArticleEvaluatorAllowed($userId, $t->typeKey);
            });
        }
        
        return $this->respond([
            'ok' => true,
            'tools' => array_map(fn($t) => [
                'type_key' => $t->typeKey,
                'title' => $t->title,
                'description' => $t->description,
            ], array_values($tools)),
        ]);
    }

    /**
     * POST /students/ai-tools/request
     * إرسال طلب جديد
     */
    public function request()
    {
        $userId = $this->userId();
        
        // Logging للتصحيح
        log_message('debug', "AI Tools Request: userId=" . ($userId ?: 'NULL'));
        
        if (!$userId) {
            log_message('debug', "AI Tools Request FAILED: No userId");
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $json = $this->request->getJSON(true) ?? [];
        $typeKey = (string) ($json['type_key'] ?? '');
        $taskId = (int) ($json['task_id'] ?? 0);

        log_message('debug', "AI Tools Request: typeKey={$typeKey}, taskId={$taskId}");

        if ($typeKey === '' || $taskId <= 0) {
            log_message('debug', "AI Tools Request FAILED: Missing typeKey or taskId");
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        // التحقق من صلاحية الوصول لأداة مقيم المقالات
        $isAllowed = $this->isArticleEvaluatorAllowed($userId, $typeKey);
        log_message('debug', "AI Tools Request: isAllowed=" . ($isAllowed ? 'true' : 'false') . " for typeKey={$typeKey}");
        
        if (!$isAllowed) {
            log_message('debug', "AI Tools Request FORBIDDEN: userId={$userId} not allowed for {$typeKey}");
            return $this->failForbidden('ليس لديك صلاحية استخدام هذه الأداة');
        }

        $service = new AiToolService();
        $result = $service->createJob($userId, $typeKey, $taskId, $json);

        if (!$result['ok']) {
            return $this->respond($result, $result['status'] ?? 400);
        }

        return $this->respond($result);
    }

    /**
     * POST /students/ai-tools/analyze-file
     * تحليل ملف ذكي (smart_analyzer_v1)
     */
    public function analyzeFile()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        // 1. استلام البيانات
        $file = $this->request->getFile('file');
        $typeKey = $this->request->getPost('type_key');
        $taskId = (int) $this->request->getPost('task_id');
        $prompt = trim((string) $this->request->getPost('prompt'));

        if (!$file || !$file->isValid()) {
            return $this->fail('الملف غير صالح', 422);
        }

        if ($typeKey !== 'smart_analyzer_v1') {
            return $this->fail('نوع الأداة غير مدعوم', 400);
        }

        if ($taskId <= 0 || $prompt === '') {
            return $this->failValidationErrors('task_id والبرومبت مطلوبان');
        }

        // التحقق من الوصول (لـ analyzeFile نحن لا نخفض النقاط لأنها معالجة مباشرة)
        $accessService = new AiToolAccessService();
        $accessCheck = $accessService->checkAccess($userId, $typeKey, $taskId);
        
        if (!$accessCheck['can_access']) {
            return $this->respond([
                'ok' => false,
                'message' => $accessCheck['message'],
                'status' => 402,
                'points_cost' => $accessCheck['points_cost'],
                'user_points' => $accessCheck['user_points'],
            ], 402);
        }

        try {
            // 2. حفظ الملف مؤقتاً
            $tempPath = WRITEPATH . 'uploads/' . uniqid('smart_') . '_' . $file->getName();
            $file->move(dirname($tempPath), basename($tempPath));

            // 3. معالجة الملف مباشرة
            $ingestionService = new \App\Services\Rag\SmartIngestionService();
            
            // إنشاء file_id مؤقت (أو يمكن حفظ الملف في جدول files)
            // للبساطة، سنستخدم معرف مؤقت
            $tempFileId = time();
            
            $result = $ingestionService->ingestFile(
                $tempPath,
                $tempFileId,
                [
                    'file_name' => $file->getName(),
                    'task_id' => $taskId,
                    'user_id' => $userId
                ],
                $prompt
            );

            // حذف الملف المؤقت
            @unlink($tempPath);

            if (!$result['ok']) {
                return $this->fail($result['message'] ?? 'فشل التحليل', 500);
            }

            // 4. حفظ النتيجة كـ job منتهي
            $db = \Config\Database::connect();
            $typeModel = new \App\Models\AiJobTypeModel();
            $jobType = $typeModel->getEnabledByKey($typeKey);

            if (!$jobType) {
                return $this->fail('نوع الأداة غير موجود', 404);
            }

            $now = date('Y-m-d H:i:s');
            // تحديد points_deducted و points_cost (لـ analyzeFile نحن لا نخفض النقاط لأنها معالجة مباشرة)
            $pointsDeducted = 0;
            $pointsCost = null;
            if ($accessCheck['reason'] === 'points' && $accessCheck['points_cost'] > 0) {
                // إذا كان يجب خصم النقاط، نخفضها هنا (لأن المعالجة مباشرة)
                $deductResult = $accessService->deductPointsIfNeeded($userId, $typeKey, $taskId);
                if ($deductResult['success'] && $deductResult['points_deducted'] > 0) {
                    $pointsDeducted = 1;
                    $pointsCost = $deductResult['points_cost'];
                }
            }
            
            $db->table('ai_jobs')->insert([
                'user_id' => $userId,
                'job_type_id' => (int)$jobType['id'],
                'task_id' => $taskId,
                'payload_json' => json_encode(['prompt' => $prompt], JSON_UNESCAPED_UNICODE),
                'result_json' => json_encode(['units' => $result['units'] ?? []], JSON_UNESCAPED_UNICODE),
                'status' => 'done',
                'retry_count' => 0,
                'points_deducted' => $pointsDeducted,
                'points_cost' => $pointsCost,
                'requested_ip' => $this->request->getIPAddress(),
                'requested_ua' => (string)$this->request->getUserAgent(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $jobId = (int)$db->insertID();

            return $this->respond([
                'ok' => true,
                'status' => 'done',
                'job_id' => $jobId,
                'message' => 'تم التحليل بنجاح',
                'units_count' => $result['units_count'] ?? 0
            ]);

        } catch (\Throwable $e) {
            // حذف الملف المؤقت في حالة الخطأ
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            
            log_message('error', 'Smart Analyzer error: ' . $e->getMessage());
            return $this->fail('حدث خطأ أثناء التحليل', 500);
        }
    }

    /**
     * GET /students/ai-tools/jobs-list
     * جلب قائمة جميع jobs لمهمة معينة
     */
    public function jobsList()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $typeKey = (string) $this->request->getGet('type_key');
        $taskId = (int) $this->request->getGet('task_id');
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = max(1, min(50, (int) ($this->request->getGet('per_page') ?: 5)));
        $search = trim((string) $this->request->getGet('search'));

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        // التحقق من صلاحية الوصول لأداة مقيم المقالات
        if (!$this->isArticleEvaluatorAllowed($userId, $typeKey)) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذه الأداة');
        }

        $typeModel = new \App\Models\AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return $this->failNotFound('نوع الأداة غير موجود');
        }

        $jobModel = new \App\Models\AiJobModel();
        $db = \Config\Database::connect();
        
        // بناء الاستعلام الأساسي
        $builder = $db->table('ai_jobs')
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->where('job_type_id', (int)$jobType['id']);

        // حساب العدد الإجمالي (قبل التصفية بالبحث)
        $total = $builder->countAllResults(false);

        // تطبيق pagination
        $offset = ($page - 1) * $perPage;
        $jobs = $builder->orderBy('created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $service = new AiToolService();
        $jobsList = [];
        
        foreach ($jobs as $job) {
            $jobData = [
                'id' => (int)$job['id'],
                'status' => (string)$job['status'],
                'created_at' => (string)$job['created_at'],
                'updated_at' => (string)$job['updated_at'],
                'points_cost' => !empty($job['points_cost']) ? (int)$job['points_cost'] : null,
                'points_deducted' => (int)($job['points_deducted'] ?? 0),
                'is_free_usage' => (int)($job['points_deducted'] ?? 0) === 0,
            ];

            // استخراج تفاصيل الطالب للأدوات المدعومة
            if (!empty($job['payload_json'])) {
                $payload = json_decode($job['payload_json'], true);
                if (is_array($payload)) {
                    if ($typeKey === 'inquiry_report_evaluator_v1') {
                        $jobData['student_name'] = $payload['student_name'] ?? '';
                        $jobData['student_id'] = $payload['student_id'] ?? '';
                    } elseif ($typeKey === 'article_evaluator_v1' || $typeKey === 'inquiry_plan_evaluator_v1') {
                        $jobData['teacher_name'] = $payload['teacher_name'] ?? '';
                        $jobData['student_number'] = $payload['student_number'] ?? '';
                    }

                    // تصفية النتائج بناءً على البحث
                    if (!empty($search)) {
                        $searchLower = mb_strtolower($search, 'UTF-8');
                        if ($typeKey === 'inquiry_report_evaluator_v1') {
                            $studentName = mb_strtolower($jobData['student_name'] ?? '', 'UTF-8');
                            $studentIdVal = mb_strtolower($jobData['student_id'] ?? '', 'UTF-8');
                            if (strpos($studentName, $searchLower) === false && 
                                strpos($studentIdVal, $searchLower) === false) {
                                continue; // تخطي هذا job لأنه لا يطابق البحث
                            }
                        } elseif ($typeKey === 'article_evaluator_v1' || $typeKey === 'inquiry_plan_evaluator_v1') {
                            $teacherName = mb_strtolower($jobData['teacher_name'] ?? '', 'UTF-8');
                            $studentNumber = mb_strtolower($jobData['student_number'] ?? '', 'UTF-8');
                            if (strpos($teacherName, $searchLower) === false && 
                                strpos($studentNumber, $searchLower) === false) {
                                continue; // تخطي هذا job لأنه لا يطابق البحث
                            }
                        }
                    }
                }
            }

            // إضافة download_url و files إذا كان status = done
            if ($job['status'] === 'done') {
                if (!empty($job['file_id'])) {
                    $jobData['file_id'] = (int)$job['file_id'];
                    $jobData['download_url'] = $service->getDownloadUrl($userId, (int)$job['file_id']);
                }

                // ✅ دعم الملفات المتعددة (تصميم الاختبار + تقييم البحث الاستقصائي)
                if (!empty($job['result_json'])) {
                    $decodedResult = json_decode((string)$job['result_json'], true);
                    if (is_array($decodedResult)) {
                        $files = [];

                        // تصميم الاختبار: 3 ملفات
                        if ($typeKey === 'test_design_v1') {
                            if (!empty($decodedResult['file_analysis_id'])) {
                                $files[] = ['name' => 'تحليل المحتوى (Word)', 'url' => $service->getDownloadUrl($userId, (int)$decodedResult['file_analysis_id'])];
                            }
                            if (!empty($decodedResult['file_excel_id'])) {
                                $files[] = ['name' => 'جدول المواصفات (Excel)', 'url' => $service->getDownloadUrl($userId, (int)$decodedResult['file_excel_id'])];
                            }
                            if (!empty($decodedResult['file_exam_id'])) {
                                $files[] = ['name' => 'الاختبار (Word)', 'url' => $service->getDownloadUrl($userId, (int)$decodedResult['file_exam_id'])];
                            }
                        }

                        // تقييم البحث الاستقصائي: ملفان
                        if ($typeKey === 'inquiry_report_evaluator_v1') {
                            if (!empty($decodedResult['file_feedback_id'])) {
                                $files[] = ['name' => 'تقرير التغذية الراجعة (Word)', 'url' => $service->getDownloadUrl($userId, (int)$decodedResult['file_feedback_id'])];
                            }
                            if (!empty($decodedResult['file_rubric_id'])) {
                                $files[] = ['name' => 'جدول معايير التقييم (Word)', 'url' => $service->getDownloadUrl($userId, (int)$decodedResult['file_rubric_id'])];
                            }
                        }

                        if (!empty($files)) {
                            $jobData['files'] = $files;
                        }
                    }
                }
            }

            // إضافة error_message إذا كان status = failed
            if ($job['status'] === 'failed' && !empty($job['error_message'])) {
                $jobData['error_message'] = (string)$job['error_message'];
            }

            $jobsList[] = $jobData;
        }

        // إعادة حساب العدد الإجمالي بعد التصفية بالبحث
        if (in_array($typeKey, ['article_evaluator_v1', 'inquiry_report_evaluator_v1', 'inquiry_plan_evaluator_v1'], true) && !empty($search)) {
            // جلب جميع jobs للبحث الكامل
            $allJobsBuilder = $db->table('ai_jobs')
                ->where('user_id', $userId)
                ->where('task_id', $taskId)
                ->where('job_type_id', (int)$jobType['id'])
                ->orderBy('created_at', 'DESC');
            
            $allJobs = $allJobsBuilder->get()->getResultArray();
            $filteredCount = 0;
            
            foreach ($allJobs as $job) {
                if (!empty($job['payload_json'])) {
                    $payload = json_decode($job['payload_json'], true);
                    if (is_array($payload)) {
                        $searchLower = mb_strtolower($search, 'UTF-8');
                        if ($typeKey === 'inquiry_report_evaluator_v1') {
                            $studentName = mb_strtolower($payload['student_name'] ?? '', 'UTF-8');
                            $studentIdVal = mb_strtolower($payload['student_id'] ?? '', 'UTF-8');
                            if (strpos($studentName, $searchLower) !== false || 
                                strpos($studentIdVal, $searchLower) !== false) {
                                $filteredCount++;
                            }
                        } else {
                            $teacherName = mb_strtolower($payload['teacher_name'] ?? '', 'UTF-8');
                            $studentNumber = mb_strtolower($payload['student_number'] ?? '', 'UTF-8');
                            if (strpos($teacherName, $searchLower) !== false || 
                                strpos($studentNumber, $searchLower) !== false) {
                                $filteredCount++;
                            }
                        }
                    }
                }
            }
            
            $total = $filteredCount;
        }

        return $this->respond([
            'ok' => true,
            'jobs' => $jobsList,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * GET /students/ai-tools/job-payload/:jobId
     * جلب payload job للتعديل
     */
    public function jobPayload(int $jobId)
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $jobModel = new \App\Models\AiJobModel();
        $job = $jobModel->find($jobId);

        if (!is_array($job)) {
            return $this->failNotFound('الطلب غير موجود');
        }

        // التحقق من أن المستخدم هو صاحب الطلب
        if ((int)$job['user_id'] !== $userId) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذا الطلب');
        }

        // جلب payload_json
        $payload = json_decode((string)$job['payload_json'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return $this->respond([
            'ok' => true,
            'payload' => $payload,
        ]);
    }

    /**
     * POST /students/ai-tools/job-update-payload/:jobId
     * تحديث payload_json لـ job
     */
    public function jobUpdatePayload(int $jobId)
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $jobModel = new \App\Models\AiJobModel();
        $job = $jobModel->find($jobId);

        if (!is_array($job)) {
            return $this->failNotFound('الطلب غير موجود');
        }

        // التحقق من أن المستخدم هو صاحب الطلب
        if ((int)$job['user_id'] !== $userId) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذا الطلب');
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            return $this->failValidationErrors('payload غير صحيح');
        }

        $db = \Config\Database::connect();
        $db->table('ai_jobs')->where('id', $jobId)->update([
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'ok' => true,
            'message' => 'تم تحديث بيانات الطلب بنجاح',
        ]);
    }

    /**
     * POST /students/ai-tools/job-reset/:jobId
     * إعادة تعيين job (تحديث status إلى pending وحذف الملف)
     */
    public function jobReset(int $jobId)
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $jobModel = new \App\Models\AiJobModel();
        $job = $jobModel->find($jobId);

        if (!is_array($job)) {
            return $this->failNotFound('الطلب غير موجود');
        }

        // التحقق من أن المستخدم هو صاحب الطلب
        if ((int)$job['user_id'] !== $userId) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذا الطلب');
        }

        $db = \Config\Database::connect();

        // حذف الملف إذا كان موجوداً
        if (!empty($job['file_id'])) {
            $fileId = (int)$job['file_id'];
            
            // جلب معلومات الملف
            $fileRow = $db->table('files')->where('id', $fileId)->get()->getRowArray();
            
            if ($fileRow) {
                // حذف السجلات المرتبطة من user_task_files أولاً (لتفادي foreign key constraint)
                if ($db->tableExists('user_task_files')) {
                    $db->table('user_task_files')->where('file_id', $fileId)->delete();
                }
                
                // حذف الملف من نظام الملفات
                $filePath = rtrim(FCPATH, '/') . '/' . ltrim($fileRow['file_path'], '/');
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
                
                // حذف السجل من جدول files
                $db->table('files')->where('id', $fileId)->delete();
            }
        }

        // تحديث job: status إلى queued و file_id إلى NULL
        $db->table('ai_jobs')->where('id', $jobId)->update([
            'status' => 'queued',
            'file_id' => null,
            'result_json' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'ok' => true,
            'message' => 'تم إعادة تعيين الطلب بنجاح',
        ]);
    }

    /**
     * GET /students/ai-tools/check-access
     * التحقق من إمكانية الوصول قبل الإرسال
     */
    public function checkAccess()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $typeKey = (string) $this->request->getGet('type_key');
        $taskId = (int) $this->request->getGet('task_id');
        $studentId = $this->request->getGet('student_id') ?: $this->request->getGet('student_number');
        if ($studentId) {
            $studentId = (string) $studentId;
        } else {
            $studentId = null;
        }

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        // التحقق من صلاحية الوصول لأداة مقيم المقالات
        if (!$this->isArticleEvaluatorAllowed($userId, $typeKey)) {
            return $this->respond([
                'ok' => true,
                'can_access' => false,
                'reason' => 'ليس لديك صلاحية استخدام هذه الأداة',
                'points_cost' => 0,
                'user_points' => 0,
                'has_active_plan' => false,
            ]);
        }

        $accessService = new AiToolAccessService();
        $result = $accessService->checkAccess($userId, $typeKey, $taskId, $studentId);

        return $this->respond([
            'ok' => true,
            'can_access' => $result['can_access'],
            'reason' => $result['reason'],
            'points_cost' => $result['points_cost'],
            'user_points' => $result['user_points'],
            'has_active_plan' => $result['has_active_plan'],
            'has_free_usage' => $result['has_free_usage'],
            'message' => $result['message'],
        ]);
    }

    /**
     * GET /students/ai-tools/placements
     * الأدوات المتاحة للسياق الحالي
     */
    public function placements()
    {
        $taskTypeId = (int) $this->request->getGet('task_type_id');
        if ($taskTypeId <= 0) {
            return $this->failValidationErrors('task_type_id مطلوب');
        }

        $universityId = $this->request->getGet('university_id');
        $courseId = $this->request->getGet('course_id');

        $universityId = ($universityId === null || $universityId === '') ? null : (int) $universityId;
        $courseId = ($courseId === null || $courseId === '') ? null : (int) $courseId;

        $taskId = $this->request->getGet('task_id');
        $taskId = ($taskId === null || $taskId === '') ? null : (int) $taskId;

        $placementsModel = new AiToolPlacementModel();
        $placements = $placementsModel->findMatching($taskTypeId, $universityId, $courseId);

        $taskOverrideTool = null;
        if ($taskId > 0) {
            $taskModel = new \App\Models\TaskModel();
            $task = $taskModel->select('ai_tool_version')->find($taskId);
            if (is_array($task) && !empty($task['ai_tool_version'])) {
                $taskOverrideTool = $task['ai_tool_version'];
            }
        }

        if ($taskOverrideTool === 'none') {
            return $this->respond([
                'ok' => true,
                'placements' => [],
            ]);
        }

        if ($taskOverrideTool) {
            if (empty($placements)) {
                $placements = [[
                    'type_key' => $taskOverrideTool,
                    'button_label' => null,
                    'button_icon' => null,
                    'min_subscription_level' => 0
                ]];
            } else {
                $placements[0]['type_key'] = $taskOverrideTool;
            }
        }

        $response = [];
        foreach ($placements as $placement) {
            $tool = AiToolRegistry::getTool((string) $placement['type_key']);
            if (!$tool || !$tool->isEnabled) {
                continue;
            }

            // التحقق من وجود form config
            $formConfig = $tool->uiConfig['form'] ?? [];
            if (empty($formConfig) || !isset($formConfig['sections']) || !is_array($formConfig['sections']) || count($formConfig['sections']) === 0) {
                log_message('warning', "AI Tool '{$tool->typeKey}' missing form configuration in ui_config_json");
                // نستمر في إرجاع الأداة لكن مع form فارغ - JavaScript سيتعامل معه
            }

            // جلب points_cost من ai_job_types
            $typeModel = new \App\Models\AiJobTypeModel();
            $jobType = $typeModel->getEnabledByKey((string)$placement['type_key']);
            $pointsCost = $jobType ? (int)($jobType['points_cost'] ?? 30) : 30;

            $response[] = [
                'type_key' => $tool->typeKey,
                'title' => $tool->title,
                'description' => $tool->description,
                'panel' => $tool->uiConfig['panel'] ?? [],
                'form' => $formConfig,
                'result_view' => $tool->uiConfig['result'] ?? [],
                'button' => [
                    'label' => $placement['button_label'] ?: $tool->title,
                    'icon' => $placement['button_icon'] ?: null,
                ],
                'min_subscription_level' => (int) ($placement['min_subscription_level'] ?? 0),
                'has_template' => !empty($tool->templatePath), // إضافة معلومات template_path
                'points_cost' => $pointsCost,
            ];
        }

        return $this->respond([
            'ok' => true,
            'placements' => $response,
        ]);
    }

    /**
     * GET /students/ai-tools/status
     * حالة الطلب
     */
    public function status()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $typeKey = (string) $this->request->getGet('type_key');
        $taskId = (int) $this->request->getGet('task_id');
        $studentId = $this->request->getGet('student_id') ?: $this->request->getGet('student_number');
        if ($studentId) {
            $studentId = (string) $studentId;
        } else {
            $studentId = null;
        }
        $jobId = $this->request->getGet('job_id') ? (int)$this->request->getGet('job_id') : null;

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        // التحقق من صلاحية الوصول لأداة مقيم المقالات
        if (!$this->isArticleEvaluatorAllowed($userId, $typeKey)) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذه الأداة');
        }

        $service = new AiToolService();
        $status = $service->getJobStatus($userId, $typeKey, $taskId, $studentId, $jobId);

        return $this->respond($status);
    }

    /**
     * GET /students/ai-tools/result
     * النتيجة
     */
    public function result()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $typeKey = (string) $this->request->getGet('type_key');
        $taskId = (int) $this->request->getGet('task_id');
        $jobId = (int) $this->request->getGet('job_id');
        $studentId = $this->request->getGet('student_id') ?: $this->request->getGet('student_number');
        if ($studentId) {
            $studentId = (string) $studentId;
        } else {
            $studentId = null;
        }

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        // التحقق من صلاحية الوصول لأداة مقيم المقالات
        if (!$this->isArticleEvaluatorAllowed($userId, $typeKey)) {
            return $this->failForbidden('ليس لديك صلاحية الوصول لهذه الأداة');
        }

        $service = new AiToolService();
        
        // إذا كان job_id محدد، استخدم getJobResultById
        if ($jobId > 0) {
            $result = $service->getJobResultById($userId, $jobId);
        } else {
            $result = $service->getJobResult($userId, $typeKey, $taskId, $studentId);
        }

        return $this->respond($result);
    }

    /**
     * POST /students/ai-tools/approve-evaluation
     * اعتماد التقييم وبناء ملفات DOCX
     * Body: { job_id, rubric_grades: [...], feedback_report: {...}, overall_evaluation: {...} }
     */
    public function approveEvaluation()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $json = $this->request->getJSON(true) ?? [];
        $jobId = (int)($json['job_id'] ?? 0);

        if ($jobId <= 0) {
            return $this->failValidationErrors('job_id مطلوب');
        }

        $db = \Config\Database::connect();
        $job = $db->table('ai_jobs')->where('id', $jobId)->get()->getRowArray();

        if (!$job) {
            return $this->failNotFound('المهمة غير موجودة');
        }
        if ((int)$job['user_id'] !== $userId) {
            return $this->failForbidden('غير مصرح');
        }
        if ($job['status'] !== 'review') {
            return $this->fail('المهمة ليست في حالة مراجعة', 400);
        }

        // دمج البيانات المعتمدة مع result_json الأصلي
        $originalResult = json_decode((string)$job['result_json'], true) ?? [];

        // استخدام العلامات المعتمدة من المستخدم (أو الأصلية إذا لم تُعدّل)
        if (!empty($json['rubric_grades'])) {
            $originalResult['rubric_grades'] = $json['rubric_grades'];
        }
        if (!empty($json['feedback_report'])) {
            $originalResult['feedback_report'] = $json['feedback_report'];
        }
        if (!empty($json['overall_evaluation'])) {
            $originalResult['overall_evaluation'] = $json['overall_evaluation'];
        }

        // جلب payload الأصلي
        $payload = json_decode((string)$job['payload_json'], true) ?? [];
        $studentId = $payload['student_id'] ?? $userId;
        $taskId = (int)$job['task_id'];

        try {
            // تمرير payload عبر global
            global $_inquiry_report_evaluator_payload;
            $_inquiry_report_evaluator_payload = $payload;

            $runner = new \App\Controllers\Cron\AiJobsRunner();
            $tplData = $runner->makeTemplateDataForDocx($originalResult, 'inquiry_report_evaluator_v1');
            $tplData['rubric_grades_raw'] = $originalResult['rubric_grades'] ?? [];

            $docx = new \App\Libraries\DocxService();

            // بناء ملف التغذية الراجعة
            $rel1 = 'downloads/inquiry_evaluations/' . $studentId . '_Inquiry_SF_AA.docx';
            $built1 = $docx->buildFeedbackReport($tplData, $rel1);
            $fileId1 = $runner->insertFileRowSmart($built1['rel_path'], $userId, (int)$built1['bytes']);
            $runner->linkTaskFileSafe($taskId, $fileId1);

            // بناء ملف الروبريك
            $rel2 = 'downloads/inquiry_evaluations/' . $studentId . '_Inquiry_GC_AA.docx';
            $built2 = $docx->buildRubricReport($tplData, $rel2);
            $fileId2 = $runner->insertFileRowSmart($built2['rel_path'], $userId, (int)$built2['bytes']);
            $runner->linkTaskFileSafe($taskId, $fileId2);

            // تحديث سجل المهمة
            $db->table('ai_jobs')->where('id', $jobId)->update([
                'result_json' => json_encode(array_merge($originalResult, [
                    'file_feedback_id' => $fileId1,
                    'file_rubric_id'   => $fileId2,
                ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'file_id'    => $fileId1,
                'status'     => 'done',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // جلب URLs للملفات
            $service = new AiToolService();
            $files = [];
            $fbUrl = $service->getDownloadUrl($userId, $fileId1);
            if ($fbUrl) $files[] = ['name' => 'تقرير التغذية الراجعة (Word)', 'url' => $fbUrl];
            $rbUrl = $service->getDownloadUrl($userId, $fileId2);
            if ($rbUrl) $files[] = ['name' => 'جدول معايير التقييم (Word)', 'url' => $rbUrl];

            return $this->respond([
                'ok' => true,
                'state' => 'done',
                'message' => 'تم اعتماد التقييم وبناء الملفات بنجاح',
                'files' => $files,
                'download_url' => $fbUrl,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'approveEvaluation failed: ' . $e->getMessage());
            return $this->fail('فشل بناء الملفات: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /students/ai-tools/regenerate-feedback
     * إعادة توليد نقاط القوة/التطوير بناءً على علامات معدّلة
     * Body: { job_id, rubric_grades: [...] }
     */
    public function regenerateFeedback()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $json = $this->request->getJSON(true) ?? [];
        $jobId = (int)($json['job_id'] ?? 0);
        $modifiedGrades = $json['rubric_grades'] ?? [];

        if ($jobId <= 0 || empty($modifiedGrades)) {
            return $this->failValidationErrors('job_id و rubric_grades مطلوبان');
        }

        $db = \Config\Database::connect();
        $job = $db->table('ai_jobs')->where('id', $jobId)->get()->getRowArray();

        if (!$job || (int)$job['user_id'] !== $userId || $job['status'] !== 'review') {
            return $this->fail('المهمة غير موجودة أو ليست في حالة مراجعة', 400);
        }

        $originalResult = json_decode((string)$job['result_json'], true) ?? [];
        $originalGrades = $originalResult['rubric_grades'] ?? [];
        $originalGradesMap = [];
        foreach ($originalGrades as $og) {
            $originalGradesMap[$og['criterion_id']] = $og;
        }

        // استخراج الطلب الأصلي لإرساله كسياق
        $requestDump = json_decode((string)$job['request_dump'], true) ?? [];
        $originalPrompt = $requestDump['contents'][0]['parts'][0]['text'] ?? 'لا يوجد بيانات للطلب الأصلي.';

        // بناء prompt مختصر لإعادة توليد التغذية الراجعة والمبررات
        $gradesText = '';
        $hasChangedGrades = false;
        foreach ($modifiedGrades as $g) {
            $cid = $g['criterion_id'] ?? '';
            $oldGrade = $originalGradesMap[$cid] ?? null;
            if ($oldGrade && $oldGrade['grade_symbol'] !== $g['grade_symbol']) {
                $hasChangedGrades = true;
                $gradesText .= "- المعيار رقم {$cid} ({$g['criterion_name']}):\n  العلامة السابقة: {$oldGrade['grade_word']} ({$oldGrade['grade_symbol']})\n  العلامة المعدلة: {$g['grade_word']} ({$g['grade_symbol']})\n  المبرر السابق: {$oldGrade['justification']}\n";
            }
        }

        // إذا لم يكن هناك أي تعديل فعلي في العلامات، نرسل العلامات فقط لإعادة التغذية الراجعة بدون مبررات
        if (!$hasChangedGrades) {
            foreach ($modifiedGrades as $g) {
                $gradesText .= "- {$g['criterion_name']}: {$g['grade_word']} ({$g['grade_symbol']})\n";
            }
        }

        log_message('debug', "regenerateFeedback: hasChangedGrades=" . ($hasChangedGrades ? 'true' : 'false'));

$regeneratePrompt = <<<PROMPT
أنت خبير تقييم تربوي.
لقد قمت مسبقاً بتقييم عمل طالب بناءً على المعطيات في "الطلب الأصلي" (المرفق في نهاية هذه الرسالة).

قام المُقيّم النهائي (مدرس المعلمين) بمراجعة تقييمك وقام بتعديل أو اعتماد العلامات النهائية للمعايير لتصبح كما يلي:
تفاصيل التقييم النهائي المعتمد:
{$gradesText}

المطلوب منك الآن ليس إعادة التقييم من الصفر، بل تقديم **تبرير للتقييم** وكتابة الملاحظات بناءً على ما اعتمده المدرس.
يجب أن تعيد صياغة المبررات للعلامات المعدلة لتتوافق منطقياً مع العلامة الجديدة، وأن تستخرج نقاط القوة ومجالات التطوير والملاحظات العامة بحيث تعكس التقييم النهائي بدقة وبنفس الجودة الأكاديمية العميقة للتقييم الأولي.

القواعد الأساسية:
1. المبررات المحدثة (updated_justifications): إن وُجدت معايير تغيرت علامتها (تم تزويدك بمبررها السابق وعلامتها المعدلة)، أعد صياغة المبرر ليبرر العلامة الجديدة بمنطقية مع الاحتفاظ بالسياق قدر الإمكان. استخدم "criterion_id" كمفتاح.
2. التغذية الراجعة: يجب أن تتضمن نقاط القوة ومجالات التطوير مبررات واضحة تعكس التقييم النهائي.
3. الطول والتفصيل: يجب أن تكون الملاحظات ونقاط القوة والتطوير مفصلة وعميقة ومبنية على محتوى عمل الطالب الموجود في الطلب الأصلي.
4. التزام شامل لجميع المعايير: يحتوي التقييم على 8 معايير. يتم مقارنة علامة كل معيار مع "العلامة الكلية" لتحديد ما إذا كان المعيار يمثل "نقطة قوة" في هذا البحث أم "مجال تطوير". يجب كتابة جملة واحدة لكل معيار (إما كنقطة قوة أو نقطة تطوير). دائماً مجموع عدد نقاط القوة ونقاط التطوير معاً يجب أن يساوي 8 بالضبط، لكن أعدادهم النسبية تختلف وتتوزع بين القسمين حسب جودة المقال وعلامته.
5. منع كتابة اسم المعيار: لا تذكر اسم المعيار في بداية الجملة مطلقاً. يجب أن تكون النقاط وصفية ومباشرة (مثلاً: "تم إظهار قدرة ممتازة على..." بدلاً من "المعرفة والفهم: تم إظهار...").
6. حافظ على نفس الصياغة والأسلوب اللغوي الممتاز واستخدم لغة محايدة جنسياً.

أرجع JSON فقط بالشكل التالي دون أي إضافات:
{
  "updated_justifications": {
    "1": "المبرر المحدث للمعيار الأول بناءً على التقييم الجديد",
    "2": "المبرر المحدث للمعيار الثاني بناءً على التقييم الجديد"
  },
  "strengths": ["نقطة تفصيلية 1 مع المبرر", "نقطة تفصيلية 2 مع المبرر"],
  "areas_for_development": ["نقطة تفصيلية 1 مع المبرر", "نقطة تفصيلية 2 مع المبرر"],
  "general_notes": "فقرة شاملة ومفصلة تلخص التقييم وتقدم إضاءات وتوصيات عامة"
}

--- بداية الطلب الأصلي (للسياق فقط) ---
{$originalPrompt}
--- نهاية الطلب الأصلي ---
PROMPT;

        try {
            // استدعاء Gemini API مباشرة لإعادة توليد التغذية الراجعة
            $apiKey = getenv('AI_GEMINI_API_KEY') ?: (getenv('GEMINI_API_KEY') ?: '');
            if (empty($apiKey)) {
                return $this->fail('مفتاح API غير متوفر', 500);
            }

            $model = 'gemini-2.5-flash';
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                 . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);

            $requestBody = [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $regeneratePrompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'response_mime_type' => 'application/json',
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$raw) {
                return $this->fail('فشل استدعاء الذكاء الاصطناعي (HTTP ' . $httpCode . ')', 500);
            }

            $resp = json_decode($raw, true);
            $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            log_message('debug', 'AI Response Text: ' . $text);

            $feedbackJson = json_decode($text, true);
            if (!is_array($feedbackJson)) {
                preg_match('/\{[\s\S]*\}/u', $text, $m);
                $feedbackJson = !empty($m[0]) ? json_decode($m[0], true) : null;
            }

            if (!$feedbackJson) {
                return $this->fail('فشل تحليل رد الذكاء الاصطناعي', 500);
            }

            // تحديث المبررات الجديدة في $modifiedGrades
            $updatedJustifications = $feedbackJson['updated_justifications'] ?? [];
            log_message('debug', 'Updated Justifications from AI: ' . json_encode($updatedJustifications, JSON_UNESCAPED_UNICODE));
            
            foreach ($modifiedGrades as &$g) {
                $cid = (string)($g['criterion_id'] ?? '');
                if (!empty($updatedJustifications[$cid])) {
                    log_message('debug', "Updating justification for CID {$cid}");
                    $g['justification'] = $updatedJustifications[$cid];
                }
            }
            unset($g);

            // تحديث result_json بالعلامات الجديدة والتغذية الراجعة المعاد توليدها
            $originalResult['rubric_grades'] = $modifiedGrades;
            $originalResult['feedback_report'] = [
                'strengths'            => $feedbackJson['strengths'] ?? [],
                'areas_for_development' => $feedbackJson['areas_for_development'] ?? [],
                'general_notes'        => $feedbackJson['general_notes'] ?? '',
            ];

            // حفظ التحديثات
            $db->table('ai_jobs')->where('id', $jobId)->update([
                'result_json' => json_encode($originalResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->respond([
                'ok' => true,
                'feedback_report' => $originalResult['feedback_report'],
                'rubric_grades' => $modifiedGrades,
                'message' => 'تم إعادة توليد التغذية الراجعة والمبررات بنجاح',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'regenerateFeedback failed: ' . $e->getMessage());
            return $this->fail('فشل إعادة التوليد: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /students/ai-tools/download/:fileId
     * تنزيل الملف
     */
    public function download(int $fileId)
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $service = new AiToolService();
        $url = $service->getDownloadUrl($userId, $fileId);

        if (!$url) {
            return $this->failNotFound('الملف غير موجود');
        }

        return redirect()->to($url);
    }

    /**
     * POST /students/ai-tools/comment-reply
     * إنشاء رد على تعليق زميل
     */
    public function commentReply()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $json = $this->request->getJSON(true) ?? [];
        $taskId = (int) ($json['task_id'] ?? 0);
        $commentText = trim((string) ($json['comment_text'] ?? ''));
        $replyType = (string) ($json['reply_type'] ?? 'agreement');
        $replyInstructions = trim((string) ($json['reply_instructions'] ?? ''));

        if ($taskId <= 0 || $commentText === '') {
            return $this->failValidationErrors('task_id و comment_text مطلوبان');
        }

        // التحقق من الحد الأقصى (ردان فقط)
        $commentModel = new \App\Models\ForumReplyCommentModel();
        if (!$commentModel->canCreateNewComment($userId, $taskId)) {
            return $this->fail('تم الوصول إلى الحد الأقصى من الردود (ردان فقط)', 400);
        }

        // جلب بيانات المهمة
        $db = \Config\Database::connect();
        $task = $db->table('tasks')->where('id', $taskId)->get()->getRowArray();
        if (!$task) {
            return $this->failNotFound('المهمة غير موجودة');
        }

        // إنشاء سجل التعليق
        $sortOrder = $commentModel->getNextSortOrder($userId, $taskId);
        $commentId = $commentModel->insert([
            'user_id' => $userId,
            'task_id' => $taskId,
            'comment_text' => $commentText,
            'reply_type' => $replyType,
            'reply_instructions' => $replyInstructions,
            'sort_order' => $sortOrder,
            'status' => 'pending',
        ]);

        // إنشاء AI job مع payload خاص
        $payload = [
            'comment_text' => $commentText,
            'reply_type' => $replyType,
            'reply_instructions' => $replyInstructions,
            'task_title' => (string)($task['title'] ?? ''),
            'task_prompt' => (string)($task['task_prompt'] ?? ''),
            'tone' => $json['tone'] ?? 'formal',
            'audience' => $json['audience'] ?? 'teachers',
            'student_answer' => trim((string)($json['student_answer'] ?? '')),
            'is_comment_reply' => true, // علامة للتمييز
        ];

        $service = new AiToolService();
        $result = $service->createJob($userId, 'forum_reply_v1', $taskId, $payload);

        if (!$result['ok']) {
            // حذف سجل التعليق في حالة الفشل
            $commentModel->delete($commentId);
            return $this->respond($result, $result['status'] ?? 400);
        }

        // ربط AI job بالتعليق
        $commentModel->update($commentId, ['ai_job_id' => $result['job_id']]);

        return $this->respond([
            'ok' => true,
            'comment_id' => $commentId,
            'job_id' => $result['job_id'],
            'message' => 'تم استلام طلب الرد على التعليق',
        ]);
    }

    /**
     * GET /students/ai-tools/comments
     * جلب جميع ردود التعليقات لمهمة معينة
     */
    public function comments()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $taskId = (int) $this->request->getGet('task_id');
        if ($taskId <= 0) {
            return $this->failValidationErrors('task_id مطلوب');
        }

        $commentModel = new \App\Models\ForumReplyCommentModel();
        $comments = $commentModel->getCommentsForUserTask($userId, $taskId);

        // جلب نتائج AI jobs
        $db = \Config\Database::connect();
        $results = [];
        foreach ($comments as $comment) {
            $result = [
                'id' => (int)$comment['id'],
                'comment_text' => $comment['comment_text'],
                'reply_type' => $comment['reply_type'],
                'reply_instructions' => $comment['reply_instructions'],
                'sort_order' => (int)$comment['sort_order'],
                'status' => $comment['status'],
            ];

            // محاولة جلب النتيجة من reply_result أولاً، ثم من ai_jobs
            if (!empty($comment['reply_result'])) {
                $decoded = json_decode($comment['reply_result'], true);
                if (is_array($decoded)) {
                    $result['reply'] = $decoded;
                }
            } elseif (!empty($comment['ai_job_id'])) {
                $job = $db->table('ai_jobs')->where('id', (int)$comment['ai_job_id'])->get()->getRowArray();
                if ($job && $job['status'] === 'done' && !empty($job['result_json'])) {
                    $result['reply'] = json_decode($job['result_json'], true);
                }
            }

            $results[] = $result;
        }

        return $this->respond([
            'ok' => true,
            'comments' => $results,
            'can_create_new' => $commentModel->canCreateNewComment($userId, $taskId),
        ]);
    }

    /**
     * DELETE /students/ai-tools/delete
     * حذف طلب AI (فقط للأدمن في وضع الاستعراض)
     */
    public function delete()
    {
        // التحقق من أن المستخدم أدمن في وضع الاستعراض
        if (!StudentViewHelper::isAdminViewingAsStudent()) {
            return $this->failForbidden('غير مصرح لك بهذا الإجراء');
        }

        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $typeKey = (string) $this->request->getGet('type_key');
        $taskId = (int) $this->request->getGet('task_id');

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        $jobId = (int) $this->request->getGet('job_id');
        if ($jobId <= 0) {
            return $this->failValidationErrors('رقم الطلب المحدّد (job_id) مطلوب للحذف');
        }

        $db = \Config\Database::connect();

        // جلب job type
        $typeModel = new \App\Models\AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        
        if (!$jobType) {
            return $this->failNotFound('نوع الأداة غير موجود');
        }

        // حذف AI job المحدد فقط
        $deleted = $db->table('ai_jobs')
            ->where('id', $jobId)
            ->where('user_id', $userId)
            ->where('job_type_id', (int)$jobType['id'])
            ->where('task_id', $taskId)
            ->delete();

        // حذف التعليقات المرتبطة (لأداة المنتدى)
        if ($typeKey === 'forum_reply_v1') {
            $commentModel = new \App\Models\ForumReplyCommentModel();
            $comments = $commentModel->where('user_id', $userId)
                ->where('task_id', $taskId)
                ->where('ai_job_id', $jobId)
                ->findAll();
            
            foreach ($comments as $comment) {
                $commentModel->delete($comment['id']);
            }
        }

        return $this->respond([
            'ok' => true,
            'message' => 'تم حذف الطلب بنجاح',
            'deleted' => $deleted > 0,
        ]);
    }

    /**
     * POST /students/ai-tools/retry
     * إعادة المحاولة: تغيير الحالة من failed إلى pending
     */
    public function retry()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $json = $this->request->getJSON(true) ?? [];
        $typeKey = (string) ($json['type_key'] ?? '');
        $taskId = (int) ($json['task_id'] ?? 0);

        if ($typeKey === '' || $taskId <= 0) {
            return $this->failValidationErrors('type_key و task_id مطلوبان');
        }

        $service = new AiToolService();
        $result = $service->retryJob($userId, $typeKey, $taskId);

        if (!$result['ok']) {
            return $this->respond($result, $result['status'] ?? 400);
        }

        return $this->respond($result);
    }

    /**
     * GET /students/ai-tools/specialties
     * جلب قائمة التخصصات حسب تخصص المستخدم
     */
    public function specialties()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $db = \Config\Database::connect();
        
        // استخدام الإعدادات الفعالة
        $userSpecialtyId = (int)(StudentViewHelper::getEffectiveSpecialtyId() ?? 0);
        
        // تحديد التخصصات المراد عرضها حسب تخصص المستخدم
        $specialtyIds = [];
        if ($userSpecialtyId === 14) {
            // إذا كان تخصص المستخدم 14، عرض فقط: 27, 28, 29, 30
            $specialtyIds = [27, 28, 29, 30];
        } elseif ($userSpecialtyId === 22) {
            // إذا كان تخصص المستخدم 22، عرض فقط: 18, 23, 24, 25, 21, 20, 26, 15, 19
            $specialtyIds = [18, 23, 24, 25, 21, 20, 26, 15, 19];
        }
        
        // جلب التخصصات المحددة
        $specialties = [];
        if (!empty($specialtyIds)) {
            $specialties = $db->table('specialties')
                ->select('id, name')
                ->whereIn('id', $specialtyIds)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }
        
        return $this->respond([
            'ok' => true,
            'specialties' => $specialties,
            'user_specialty_id' => $userSpecialtyId,
            'show_specialty_selector' => in_array($userSpecialtyId, [22, 14], true),
        ]);
    }

    /**
     * POST /students/ai-tools/debug-rag
     * للتشخيص: عرض جميع معلومات RAG والـ prompts
     */
    public function debugRag()
    {
        try {
            $userId = $this->userId();
            if (!$userId) {
                return $this->failUnauthorized('يجب تسجيل الدخول');
            }

            $json = $this->request->getJSON(true) ?? [];
            $question = trim((string)($json['question'] ?? ''));
            $wordCount = (int)($json['word_count'] ?? 500);
            $format = trim((string)($json['format'] ?? 'essay'));

            if ($question === '') {
                return $this->failValidationErrors('question مطلوب');
            }

        // إنشاء payload كما في buildUserPrompt
        $payload = [
            'question' => $question,
            'word_count' => $wordCount,
            'format' => $format,
        ];

        // استخدام AcademicAnswerPromptMaker
        $promptMaker = new \App\Libraries\PromptMaker\AcademicAnswerPromptMaker();
        
        // جلب EmbeddingService و RagSearchService للوصول المباشر
        $embeddingService = new \App\Services\Rag\EmbeddingService();
        $embeddingDim = $embeddingService->getDimension();
        
        // استخدام Reflection للحصول على الـ model الفعلي المستخدم
        $reflection = new \ReflectionClass($embeddingService);
        $modelProperty = $reflection->getProperty('model');
        $actualModel = $modelProperty->getValue($embeddingService);
        
        $providerProperty = $reflection->getProperty('provider');
        $actualProvider = $providerProperty->getValue($embeddingService);
        
        $ragSearch = new \App\Services\Rag\RagSearchService($embeddingDim);

        // 1) إنشاء embedding للسؤال
        $queryEmbedding = $embeddingService->createEmbedding($question);
        $actualDimension = $queryEmbedding !== null ? count($queryEmbedding) : 0;
        $dimensionMatch = ($actualDimension === $embeddingDim);
        
        $embeddingInfo = [
            'created' => $queryEmbedding !== null,
            'dimension' => $actualDimension,
            'expected_dimension' => $embeddingDim,
            'dimension_match' => $dimensionMatch,
            'warning' => !$dimensionMatch ? "⚠️ تحذير: بعد الـ embedding ({$actualDimension}) لا يطابق البعد المتوقع ({$embeddingDim}). embeddings في قاعدة البيانات هي {$embeddingDim}، لذلك لن يعمل البحث الدلالي. يجب استخدام نفس الـ model الذي استُخدم لإنشاء embeddings في قاعدة البيانات (mistral-embed)." : null,
            'provider' => $actualProvider,
            'model' => $actualModel,
            'model_from_env' => getenv('EMBEDDING_MISTRAL_MODEL') ?: getenv('EMBEDDING_OPENAI_MODEL') ?: 'not_set',
            'solution' => !$dimensionMatch ? "الحل: اضبط EMBEDDING_MISTRAL_MODEL=mistral-embed في ملف .env (وليس mistral-embed-large أو أي model آخر)" : null,
            'first_10_values' => $queryEmbedding !== null ? array_slice($queryEmbedding, 0, 10) : null,
        ];

        // 2) البحث في المراجع
        $semanticResults = [];
        $keywordResults = [];
        $hybridResults = [];
        $context = '';

        if ($queryEmbedding !== null) {
            // Semantic search
            $semanticResults = $ragSearch->semanticSearch($queryEmbedding, 10);
            
            // Keyword search
            $keywordResults = $ragSearch->keywordSearch($question, 10);
            
            // Hybrid search
            $hybridResults = $ragSearch->hybridSearch($question, $queryEmbedding, 10, 0.7);
            
            // Format context
            $context = $ragSearch->formatContext($hybridResults, 5000);
        }

        // 3) بناء prompts
        $systemPrompt = $promptMaker->buildSystemPrompt('', $payload);
        $userPrompt = $promptMaker->buildUserPrompt($payload);

        // 4) استخراج قائمة الملفات المستخدمة
        $usedFiles = [];
        foreach ($hybridResults as $result) {
            $fileName = is_array($result)
                ? ($result['file_name'] ?? 'غير معروف')
                : ($result->file_name ?? 'غير معروف');
            if (!in_array($fileName, $usedFiles, true) && $fileName !== 'غير معروف') {
                $usedFiles[] = $fileName;
            }
        }

        // 5) إرجاع جميع المعلومات
        return $this->respond([
            'ok' => true,
            'debug_info' => [
                'question' => $question,
                'word_count' => $wordCount,
                'format' => $format,
                'embedding' => $embeddingInfo,
                'search' => [
                    'semantic_results_count' => count($semanticResults),
                    'semantic_results' => array_map(function($r) {
                        return [
                            'file_name' => $r['file_name'] ?? 'غير معروف',
                            'chunk_index' => $r['chunk_index'] ?? 0,
                            'similarity' => $r['similarity'] ?? 0,
                            'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                        ];
                    }, $semanticResults),
                    'keyword_results_count' => count($keywordResults),
                    'keyword_results' => array_map(function($r) {
                        return [
                            'file_name' => $r['file_name'] ?? 'غير معروف',
                            'chunk_index' => $r['chunk_index'] ?? 0,
                            'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                        ];
                    }, $keywordResults),
                    'hybrid_results_count' => count($hybridResults),
                    'hybrid_results' => array_map(function($r) {
                        return [
                            'file_name' => $r['file_name'] ?? 'غير معروف',
                            'chunk_index' => $r['chunk_index'] ?? 0,
                            'similarity' => $r['similarity'] ?? 0,
                            'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                        ];
                    }, $hybridResults),
                ],
                'context' => [
                    'length' => mb_strlen($context),
                    'preview' => mb_substr($context, 0, 500) . (mb_strlen($context) > 500 ? '...' : ''),
                    'full' => $context,
                ],
                'used_files' => $usedFiles,
                'prompts' => [
                    'system_prompt' => $systemPrompt,
                    'user_prompt' => $userPrompt,
                    'user_prompt_length' => mb_strlen($userPrompt),
                ],
            ],
        ]);
        } catch (\Exception $e) {
            log_message('error', 'RAG Debug Error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->respond([
                'ok' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * POST /debug/rag?key=SECRET
     * للتشخيص: نسخة عامة بدون auth (محمية بمفتاح سري)
     */
    public function debugRagPublic()
    {
        // التحقق من المفتاح السري
        $secretKey = getenv('RAG_DEBUG_SECRET_KEY') ?: 'change-this-secret-key';
        $providedKey = $this->request->getGet('key') ?? $this->request->getHeaderLine('X-Debug-Key');
        
        if ($providedKey !== $secretKey) {
            return $this->respond([
                'ok' => false,
                'error' => 'Invalid debug key. Set ?key=SECRET or X-Debug-Key header.',
            ], 401);
        }

        // نفس منطق debugRag لكن بدون التحقق من userId
        try {
            $json = $this->request->getJSON(true) ?? [];
            $question = trim((string)($json['question'] ?? ''));
            $wordCount = (int)($json['word_count'] ?? 500);
            $format = trim((string)($json['format'] ?? 'essay'));

            if ($question === '') {
                return $this->respond([
                    'ok' => false,
                    'error' => 'question مطلوب',
                ], 400);
            }

            // إنشاء payload
            $payload = [
                'question' => $question,
                'word_count' => $wordCount,
                'format' => $format,
            ];

            // استخدام AcademicAnswerPromptMaker
            $promptMaker = new \App\Libraries\PromptMaker\AcademicAnswerPromptMaker();
            
            // جلب EmbeddingService و RagSearchService
            $embeddingService = new \App\Services\Rag\EmbeddingService();
            $embeddingDim = $embeddingService->getDimension();
            
            // التحقق من البعد الفعلي في قاعدة البيانات
            $db = \Config\Database::connect();
            $dbDimensionCheck = $db->table('pdf_embeddings')
                ->select('embedding_dimension, JSON_LENGTH(embedding) as actual_dim')
                ->limit(1)
                ->get()
                ->getRowArray();
            
            $dbActualDimension = $dbDimensionCheck ? (int)($dbDimensionCheck['actual_dim'] ?? $dbDimensionCheck['embedding_dimension'] ?? 0) : 0;
            $dbStoredDimension = $dbDimensionCheck ? (int)($dbDimensionCheck['embedding_dimension'] ?? 0) : 0;
            
            // استخدام Reflection للحصول على الـ model الفعلي
            $reflection = new \ReflectionClass($embeddingService);
            $modelProperty = $reflection->getProperty('model');
            $actualModel = $modelProperty->getValue($embeddingService);
            
            $providerProperty = $reflection->getProperty('provider');
            $actualProvider = $providerProperty->getValue($embeddingService);
            
            $ragSearch = new \App\Services\Rag\RagSearchService($embeddingDim);

            // 1) إنشاء embedding للسؤال (استخدام RETRIEVAL_QUERY لـ Gemini)
            $queryEmbedding = $embeddingService->createEmbedding($question, 'RETRIEVAL_QUERY');
            $actualDimension = $queryEmbedding !== null ? count($queryEmbedding) : 0;
            $dimensionMatch = ($actualDimension === $embeddingDim);
            
            // تحديد المشكلة والحل
            $dimensionIssue = '';
            $solution = '';
            
            if (!$dimensionMatch) {
                if ($dbActualDimension > 0 && $actualDimension !== $dbActualDimension) {
                    $dimensionIssue = "⚠️ تحذير: بعد الـ embedding للسؤال ({$actualDimension}) لا يطابق البعد الفعلي في قاعدة البيانات ({$dbActualDimension}).";
                    $solution = "الحل: يجب استخدام نفس الـ model الذي استُخدم لإنشاء embeddings في قاعدة البيانات. إذا كانت embeddings في قاعدة البيانات هي {$dbActualDimension}، يجب استخدام model يعطي نفس البعد.";
                } else {
                    $dimensionIssue = "⚠️ تحذير: بعد الـ embedding ({$actualDimension}) لا يطابق البعد المتوقع ({$embeddingDim}).";
                    $solution = "الحل: اضبط EMBEDDING_DIMENSION في .env أو استخدم model متوافق.";
                }
            }
            
            $embeddingInfo = [
                'created' => $queryEmbedding !== null,
                'dimension' => $actualDimension,
                'expected_dimension' => $embeddingDim,
                'dimension_match' => $dimensionMatch,
                'db_actual_dimension' => $dbActualDimension,
                'db_stored_dimension' => $dbStoredDimension,
                'warning' => $dimensionIssue,
                'solution' => $solution,
                'provider' => $actualProvider,
                'model' => $actualModel,
                'model_from_env' => getenv('EMBEDDING_MISTRAL_MODEL') ?: getenv('EMBEDDING_OPENAI_MODEL') ?: 'not_set',
                'first_10_values' => $queryEmbedding !== null ? array_slice($queryEmbedding, 0, 10) : null,
            ];

            // 2) البحث في المراجع
            $semanticResults = [];
            $keywordResults = [];
            $hybridResults = [];
            $context = '';

            if ($queryEmbedding !== null) {
                // Semantic search
                $semanticResults = $ragSearch->semanticSearch($queryEmbedding, 10);
                
                // Keyword search
                $keywordResults = $ragSearch->keywordSearch($question, 10);
                
                // Hybrid search
                $hybridResults = $ragSearch->hybridSearch($question, $queryEmbedding, 10, 0.7);
                
                // Format context
                $context = $ragSearch->formatContext($hybridResults, 5000);
            }

            // 3) بناء prompts
            $systemPrompt = $promptMaker->buildSystemPrompt('', $payload);
            $userPrompt = $promptMaker->buildUserPrompt($payload);

            // 4) استخراج قائمة الملفات المستخدمة
            $usedFiles = [];
            foreach ($hybridResults as $result) {
                $fileName = is_array($result)
                    ? ($result['file_name'] ?? 'غير معروف')
                    : ($result->file_name ?? 'غير معروف');
                if (!in_array($fileName, $usedFiles, true) && $fileName !== 'غير معروف') {
                    $usedFiles[] = $fileName;
                }
            }

            // 5) إرجاع جميع المعلومات
            return $this->respond([
                'ok' => true,
                'debug_info' => [
                    'question' => $question,
                    'word_count' => $wordCount,
                    'format' => $format,
                    'embedding' => $embeddingInfo,
                    'search' => [
                        'semantic_results_count' => count($semanticResults),
                        'semantic_results' => array_map(function($r) {
                            return [
                                'file_name' => $r['file_name'] ?? 'غير معروف',
                                'chunk_index' => $r['chunk_index'] ?? 0,
                                'similarity' => $r['similarity'] ?? 0,
                                'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                            ];
                        }, $semanticResults),
                        'keyword_results_count' => count($keywordResults),
                        'keyword_results' => array_map(function($r) {
                            return [
                                'file_name' => $r['file_name'] ?? 'غير معروف',
                                'chunk_index' => $r['chunk_index'] ?? 0,
                                'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                            ];
                        }, $keywordResults),
                        'hybrid_results_count' => count($hybridResults),
                        'hybrid_results' => array_map(function($r) {
                            return [
                                'file_name' => $r['file_name'] ?? 'غير معروف',
                                'chunk_index' => $r['chunk_index'] ?? 0,
                                'similarity' => $r['similarity'] ?? 0,
                                'text_preview' => mb_substr($r['text'] ?? '', 0, 200) . '...',
                            ];
                        }, $hybridResults),
                    ],
                    'context' => [
                        'length' => mb_strlen($context),
                        'preview' => mb_substr($context, 0, 500) . (mb_strlen($context) > 500 ? '...' : ''),
                        'full' => $context,
                    ],
                    'used_files' => $usedFiles,
                    'prompts' => [
                        'system_prompt' => $systemPrompt,
                        'user_prompt' => $userPrompt,
                        'user_prompt_length' => mb_strlen($userPrompt),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', 'RAG Debug Error: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return $this->respond([
                'ok' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * GET /students/ai-tools/get-task-prompt
     * جلب task_prompt من المهمة
     */
    public function getTaskPrompt()
    {
        $userId = $this->userId();
        if (!$userId) {
            return $this->failUnauthorized('يجب تسجيل الدخول');
        }

        $taskId = (int) $this->request->getGet('task_id');
        if ($taskId <= 0) {
            return $this->failValidationErrors('task_id مطلوب');
        }

        $db = \Config\Database::connect();
        $task = $db->table('tasks')
            ->select('task_prompt')
            ->where('id', $taskId)
            ->get()
            ->getRowArray();

        if (!$task) {
            return $this->failNotFound('المهمة غير موجودة');
        }

        return $this->respond([
            'ok' => true,
            'task_prompt' => (string)($task['task_prompt'] ?? ''),
        ]);
    }

    /**
     * التحقق من أن المستخدم مسموح له باستخدام الأدوات المقيدة للأدمن
     *
     * الأدوات المقيدة حالياً (تُضبط في .env):
     *   - article_evaluator_v1       → ARTICLE_EVALUATOR_ALLOWED_USERS
     *   - inquiry_plan_evaluator_v1  → ARTICLE_EVALUATOR_ALLOWED_USERS (نفس القائمة)
     */
    protected function isArticleEvaluatorAllowed(int $userId, string $typeKey): bool
    {
        // خريطة type_key → متغير .env
        $restrictions = [
            'article_evaluator_v1'        => 'ARTICLE_EVALUATOR_ALLOWED_USERS',
            'inquiry_plan_evaluator_v1'   => 'ARTICLE_EVALUATOR_ALLOWED_USERS',
            'inquiry_report_evaluator_v1' => 'ARTICLE_EVALUATOR_ALLOWED_USERS',
        ];

        // إذا لم تكن الأداة مقيدة → السماح لجميع المستخدمين
        if (!array_key_exists($typeKey, $restrictions)) {
            return true;
        }

        $envKey          = $restrictions[$typeKey];
        $allowedUsersStr = env($envKey, '974,500,501,1021');
        $allowedUsers    = array_map('intval', array_filter(explode(',', $allowedUsersStr)));

        log_message('debug', "Admin-Only Tool Access Check [{$typeKey}]: userId={$userId}, envKey={$envKey}, allowedStr={$allowedUsersStr}");

        $isAllowed = in_array($userId, $allowedUsers, true);
        log_message('debug', "Admin-Only Tool Access Result [{$typeKey}]: userId={$userId}, isAllowed=" . ($isAllowed ? 'YES' : 'NO'));

        return $isAllowed;
    }
}

