<?php
declare(strict_types=1);

namespace App\Services\AiTools;

use App\Models\AiJobModel;
use App\Models\AiJobTypeModel;
use Config\Database;

/**
 * AiToolService: منطق الأعمال لأدوات الذكاء الاصطناعي
 */
class AiToolService
{
    /**
     * إنشاء مهمة جديدة
     */
    public function createJob(int $userId, string $typeKey, int $taskId, array $payload): array
    {
        $db = Database::connect();
        $request = service('request');

        // 1) التحقق من وجود الأداة
        $tool = AiToolRegistry::getTool($typeKey);
        if (!$tool) {
            return ['ok' => false, 'message' => 'الأداة غير موجودة أو معطلة', 'status' => 404];
        }

        // 2) جلب job_type_id
        $typeModel = new AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return ['ok' => false, 'message' => 'نوع المهمة غير موجود', 'status' => 404];
        }

        // 3) التحقق من وجود طلب نشط فقط (pending/processing/queued)
        // السماح بإنشاء jobs متعددة، لكن منع إنشاء job جديد إذا كان هناك job نشط
        $jobModel = new AiJobModel();
        $studentId = !empty($payload['student_id']) ? (string)$payload['student_id'] : (!empty($payload['student_number']) ? (string)$payload['student_number'] : null);
        $activeJob = $jobModel->findActiveJobForUserTaskType($userId, $taskId, (int)$jobType['id'], $studentId);
        if ($activeJob) {
            $status = $activeJob['status'];
            return [
                'ok' => true,
                'status' => 'already_active',
                'state' => $status === 'queued' ? 'pending' : $status,
                'job_id' => (int)$activeJob['id'],
            ];
        }

        // 3.5) التحقق من الوصول وخصم النقاط
        $accessService = new AiToolAccessService();
        $deductResult = $accessService->deductPointsIfNeeded($userId, $typeKey, $taskId, $studentId);
        
        if (!$deductResult['success']) {
            return [
                'ok' => false,
                'message' => $deductResult['message'],
                'status' => 402, // Payment Required
                'points_cost' => $deductResult['points_cost'],
                'user_points' => $deductResult['remaining_points'],
            ];
        }
        
        // تحديد قيم points_deducted و points_cost
        $pointsDeducted = $deductResult['points_deducted'] > 0 ? 1 : 0;
        $pointsCost = $deductResult['points_cost'];

        // 4) جلب بيانات المهمة إذا كانت الأداة تحتاجها (needs_task_context من قاعدة البيانات)
        if ($tool->needsTaskContext) {
            $task = $db->table('tasks')->where('id', $taskId)->get()->getRowArray();
            if ($task) {
                $payload['task_title']  = (string)($task['title'] ?? '');
                $payload['task_prompt'] = (string)($task['task_prompt'] ?? '');
            }
        }

        // 5) جلب تخصص المعلم إذا كانت الأداة تحتاجه (needs_specialty من قاعدة البيانات)
        if ($tool->needsSpecialty) {
            // إذا كان المستخدم قد اختار تخصصاً محدداً (selected_specialty_id)، استخدمه
            if (!empty($payload['selected_specialty_id'])) {
                $selectedSpecialtyId = (int)$payload['selected_specialty_id'];
                $selectedSpecialty = $db->table('specialties')
                    ->select('name')
                    ->where('id', $selectedSpecialtyId)
                    ->get()
                    ->getRowArray();

                if ($selectedSpecialty && !empty($selectedSpecialty['name'])) {
                    $payload['teacher_specialty'] = (string)$selectedSpecialty['name'];
                }
            } else {
                // استخدام تخصص المستخدم الافتراضي
                $userSpecialty = $db->table('diploma_users du')
                    ->select('s.name AS specialty_name')
                    ->join('specialties s', 's.id = du.specialty_id', 'left')
                    ->where('du.user_id', $userId)
                    ->get()
                    ->getRowArray();

                if ($userSpecialty && !empty($userSpecialty['specialty_name'])) {
                    $payload['teacher_specialty'] = (string)$userSpecialty['specialty_name'];
                }
            }
        }

        // 5.5) حفظ task_prompt_mode في الـ payload ليستخدمه PromptMaker لاحقاً
        $payload['__task_prompt_mode'] = $tool->taskPromptMode;

        // 6) إنشاء المهمة
        $now = date('Y-m-d H:i:s');
        $payload['__meta'] = [
            'ip' => $request->getIPAddress(),
            'ua' => (string)($request->getUserAgent() ?? ''),
            'at' => $now,
            'src' => 'students/ui',
        ];

        $db->table('ai_jobs')->insert([
            'user_id' => $userId,
            'job_type_id' => (int)$jobType['id'],
            'task_id' => $taskId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'queued',
            'retry_count' => 0,
            'points_deducted' => $pointsDeducted,
            'points_cost' => $pointsCost > 0 ? $pointsCost : null,
            'requested_ip' => $request->getIPAddress(),
            'requested_ua' => (string)($request->getUserAgent() ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $jobId = (int)$db->insertID();

        return [
            'ok' => true,
            'status' => 'accepted',
            'job_id' => $jobId,
            'state' => 'queued',
            'message' => 'تم استلام الطلب وسيُعالَج في الخلفية',
            'points_deducted' => $pointsDeducted > 0,
            'points_cost' => $pointsCost,
            'remaining_points' => $deductResult['remaining_points'],
            'is_free_usage' => $pointsDeducted === 0,
        ];
    }

    /**
     * جلب حالة المهمة
     */
    public function getJobStatus(int $userId, string $typeKey, int $taskId, ?string $studentId = null, ?int $jobId = null): array
    {
        $tool = AiToolRegistry::getTool($typeKey);
        if (!$tool) {
            return ['ok' => false, 'message' => 'الأداة غير موجودة'];
        }

        $typeModel = new AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return ['ok' => false, 'message' => 'نوع المهمة غير موجود'];
        }

        $jobModel = new AiJobModel();
        if ($jobId !== null && $jobId > 0) {
            $job = $jobModel->find($jobId);
            if ($job && (int)$job['user_id'] !== $userId) {
                return ['ok' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه المهمة'];
            }
        } else {
            $job = $jobModel->findForUserTaskType($userId, $taskId, (int)$jobType['id'], $studentId);
        }

        if (!$job) {
            return ['ok' => true, 'state' => 'none'];
        }

        $status = (string)($job['status'] ?? '');
        $state = ($status === 'queued') ? 'pending' : $status;

        $result = [
            'ok' => true,
            'state' => $state,
            'job_id' => (int)$job['id'],
        ];

        if ($status === 'done') {
            $url = !empty($job['file_id']) ? $this->resolveDownloadUrl((int)$job['file_id']) : null;
            $result['download_url'] = $url;

            // ✅ دعم الملفات المتعددة
            if (!empty($job['result_json'])) {
                $decoded = json_decode((string)$job['result_json'], true);
                if (is_array($decoded)) {
                    $files = $this->buildMultiFileList($decoded, $typeKey);
                    if (!empty($files)) {
                        $result['files'] = $files;
                    }
                }
            }
        } elseif ($status === 'review') {
            // ✅ حالة المراجعة: إرجاع بيانات التقييم للعرض في المودال
            if (!empty($job['result_json'])) {
                $decoded = json_decode((string)$job['result_json'], true);
                if (is_array($decoded)) {
                    if ($typeKey === 'formative_essay_evaluator_v1') {
                        // أداة التقييم التكويني: تحمل report (4 مجالات Gibbs) + annotations
                        $result['evaluation_data'] = [
                            'report'      => $decoded['report'] ?? [],
                            'annotations' => $decoded['annotations'] ?? [],
                        ];
                    } else {
                        $result['evaluation_data'] = [
                            'rubric_grades'      => $decoded['rubric_grades'] ?? [],
                            'feedback_report'    => $decoded['feedback_report'] ?? [],
                            'overall_evaluation' => $decoded['overall_evaluation'] ?? [],
                            'student_info'       => $decoded['student_info'] ?? [],
                        ];
                    }
                }
            }
        } elseif ($status === 'failed') {
            $result['message'] = (string)($job['error_message'] ?? 'فشل سابق');
        }

        return $result;
    }

    /**
     * جلب نتيجة المهمة
     */
    public function getJobResult(int $userId, string $typeKey, int $taskId, ?string $studentId = null): array
    {
        $status = $this->getJobStatus($userId, $typeKey, $taskId, $studentId);
        if (!$status['ok'] || $status['state'] !== 'done') {
            return $status;
        }

        $typeModel = new AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return ['ok' => false, 'message' => 'نوع المهمة غير موجود'];
        }

        $jobModel = new AiJobModel();
        $job = $jobModel->findForUserTaskType($userId, $taskId, (int)$jobType['id'], $studentId);
        if (!$job || $job['status'] !== 'done') {
            return ['ok' => false, 'message' => 'المهمة غير موجودة أو لم تكتمل'];
        }

        $result = json_decode((string)$job['result_json'], true);
        if (!is_array($result)) {
            return ['ok' => false, 'message' => 'صيغة النتيجة غير صالحة'];
        }

        $ret = [
            'ok' => true,
            'state' => 'done',
            'result' => $result,
            'download_url' => !empty($job['file_id']) ? $this->resolveDownloadUrl((int)$job['file_id']) : null,
            'job_id' => (int)$job['id'],
        ];

        // ✅ دعم الملفات المتعددة
        $files = $this->buildMultiFileList($result, $typeKey);
        if (!empty($files)) {
            $ret['files'] = $files;
        }

        return $ret;
    }

    /**
     * جلب نتيجة job محدد بالـ ID
     */
    public function getJobResultById(int $userId, int $jobId): array
    {
        $jobModel = new AiJobModel();
        $job = $jobModel->find($jobId);
        
        if (!$job) {
            return ['ok' => false, 'message' => 'المهمة غير موجودة'];
        }

        // التحقق من أن job يخص المستخدم
        if ((int)$job['user_id'] !== $userId) {
            return ['ok' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه المهمة'];
        }

        if ($job['status'] !== 'done') {
            return ['ok' => false, 'message' => 'المهمة لم تكتمل بعد'];
        }

        $result = json_decode((string)$job['result_json'], true);
        if (!is_array($result)) {
            return ['ok' => false, 'message' => 'صيغة النتيجة غير صالحة'];
        }

        $ret = [
            'ok' => true,
            'state' => 'done',
            'result' => $result,
            'download_url' => !empty($job['file_id']) ? $this->resolveDownloadUrl((int)$job['file_id']) : null,
            'job_id' => (int)$job['id'],
        ];

        // ✅ دعم الملفات المتعددة لطلبات سابقة
        $typeModel = new AiJobTypeModel();
        $jobTypeRow = $typeModel->find((int)$job['job_type_id']);
        $resolvedTypeKey = $jobTypeRow ? ($jobTypeRow['type_key'] ?? '') : '';
        $files = $this->buildMultiFileList($result, $resolvedTypeKey);
        if (!empty($files)) {
            $ret['files'] = $files;
        }

        return $ret;
    }

    /**
     * ✅ بناء قائمة الملفات المتعددة بناءً على نوع الأداة
     * دالة مركزية تُستخدم من getJobStatus وgetJobResult وgetJobResultById
     */
    protected function buildMultiFileList(array $decoded, string $typeKey): array
    {
        $files = [];

        // أداة تصميم الاختبار: 3 ملفات
        if ($typeKey === 'test_design_v1') {
            if (!empty($decoded['file_analysis_id'])) {
                $files[] = ['name' => 'تحليل المحتوى (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_analysis_id'])];
            }
            if (!empty($decoded['file_excel_id'])) {
                $files[] = ['name' => 'جدول المواصفات (Excel)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_excel_id'])];
            }
            if (!empty($decoded['file_exam_id'])) {
                $files[] = ['name' => 'الاختبار (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_exam_id'])];
            }
        }

        // أداة تقييم البحث الاستقصائي: ملفان
        if ($typeKey === 'inquiry_report_evaluator_v1') {
            if (!empty($decoded['file_feedback_id'])) {
                $files[] = ['name' => 'تقرير التغذية الراجعة (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_feedback_id'])];
            }
            if (!empty($decoded['file_rubric_id'])) {
                $files[] = ['name' => 'جدول معايير التقييم (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_rubric_id'])];
            }
        }

        // أداة تقييم المقالة التأملية الختامية: ملفان
        if ($typeKey === 'final_essay_evaluator_v1') {
            if (!empty($decoded['file_feedback_id'])) {
                $files[] = ['name' => 'تقرير التغذية الراجعة الختامي (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_feedback_id'])];
            }
            if (!empty($decoded['file_rubric_id'])) {
                $files[] = ['name' => 'مقاييس الدرجة التحصيلية (Word)', 'url' => $this->resolveDownloadUrl((int)$decoded['file_rubric_id'])];
            }
        }

        // أداة التقييم التكويني للمقالة: ملفان (المقالة المُعلَّقة + التقرير)
        if ($typeKey === 'formative_essay_evaluator_v1') {
            return $this->buildFormativeFiles($decoded);
        }

        return $files;
    }

    /**
     * حل رابط التنزيل
     */
    /**
     * ✅ قائمة ملفات المقيّم التكويني (4 ملفات): تقرير Word/PDF + مقالة مُعلَّقة Word/PDF.
     * الروابط عبر download-attach لإجبار التنزيل (Content-Disposition: attachment) بالاسم وفق الاتفاقية.
     */
    public function buildFormativeFiles(array $decoded): array
    {
        $map = [
            ['file_report_id',        'تقرير التغذية الراجعة البنائي (Word)'],
            ['file_report_pdf_id',    'تقرير التغذية الراجعة البنائي (PDF)'],
            ['file_annotated_id',     'المقالة الأصلية مع التعليقات (Word)'],
            ['file_annotated_pdf_id', 'المقالة الأصلية مع التعليقات (PDF)'],
        ];
        $files = [];
        foreach ($map as [$k, $label]) {
            if (!empty($decoded[$k])) {
                $id = (int) $decoded[$k];
                $files[] = [
                    'id'   => $id,
                    'name' => $label,
                    'url'  => site_url('students/ai-tools/download-attach/' . $id),
                ];
            }
        }
        return $files;
    }

    protected function resolveDownloadUrl(int $fileId): ?string
    {
        $db = Database::connect();
        $file = $db->table('files')->where('id', $fileId)->get()->getRowArray();
        if (!$file) return null;

        $path = trim((string)($file['file_path'] ?? ''), '/');
        if ($path !== '') {
            return site_url($path);
        }

        return "/students/ai-tools/download/{$fileId}";
    }

    /**
     * جلب رابط التنزيل (للاستخدام من Controller)
     */
    public function getDownloadUrl(int $userId, int $fileId): ?string
    {
        // التحقق من أن الملف يخص المستخدم
        $db = Database::connect();
        
        // 1) التحقق مما إذا كان الملف هو الملف الرئيسي للمهمة
        $job = $db->table('ai_jobs')
            ->where('file_id', $fileId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();
        
        if ($job) {
            return $this->resolveDownloadUrl($fileId);
        }

        // 2) التحقق مما إذا كان الملف الإضافي (كأداة تصميم الاختبار) موجوداً في result_json
        // نقوم بجلب المهام التي تملك نتيجة للبحث فيها
        $jobsWithResult = $db->table('ai_jobs')
            ->where('user_id', $userId)
            ->where('result_json IS NOT NULL')
            ->like('result_json', (string)$fileId) // بحث سريع مبدئي
            ->get()
            ->getResultArray();

        foreach ($jobsWithResult as $jr) {
            $rj = json_decode((string)$jr['result_json'], true);
            if (is_array($rj)) {
                // نبحث في كل القيم للتحقق من تطابق رقم الملف
                if (in_array($fileId, array_values($rj), true) || in_array((string)$fileId, array_values($rj), true)) {
                    return $this->resolveDownloadUrl($fileId);
                }
            }
        }

        return null;
    }

    /**
     * إعادة المحاولة: تغيير الحالة من failed إلى pending
     */
    public function retryJob(int $userId, string $typeKey, int $taskId): array
    {
        $tool = AiToolRegistry::getTool($typeKey);
        if (!$tool) {
            return ['ok' => false, 'message' => 'الأداة غير موجودة أو معطلة', 'status' => 404];
        }

        $typeModel = new AiJobTypeModel();
        $jobType = $typeModel->getEnabledByKey($typeKey);
        if (!$jobType) {
            return ['ok' => false, 'message' => 'نوع المهمة غير موجود', 'status' => 404];
        }

        $jobModel = new AiJobModel();
        $job = $jobModel->findForUserTaskType($userId, $taskId, (int)$jobType['id']);

        if (!$job) {
            return ['ok' => false, 'message' => 'لم يتم العثور على مهمة سابقة', 'status' => 404];
        }

        $status = (string)($job['status'] ?? '');
        if ($status !== 'failed') {
            return ['ok' => false, 'message' => 'يمكن إعادة المحاولة فقط للمهام الفاشلة', 'status' => 400];
        }

        // التحقق من الحد الأقصى للمحاولات
        $maxRetries = (int)(getenv('AI_MAX_RETRY') ?: 5);
        $currentRetryCount = (int)($job['retry_count'] ?? 0);
        
        if ($currentRetryCount >= $maxRetries) {
            return [
                'ok' => false,
                'message' => "تم الوصول إلى الحد الأقصى للمحاولات ({$maxRetries}). لا يمكن إعادة المحاولة.",
                'status' => 400
            ];
        }

        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        
        $db->table('ai_jobs')
            ->where('id', (int)$job['id'])
            ->where('user_id', $userId)
            ->update([
                'status' => 'pending',
                'error_message' => null,
                'retry_count' => $currentRetryCount + 1, // زيادة عداد المحاولات
                'updated_at' => $now,
            ]);

        return [
            'ok' => true,
            'status' => 'retried',
            'job_id' => (int)$job['id'],
            'state' => 'pending',
            'message' => 'تم تغيير الحالة إلى معلق — سيتم معالجة المهمة مرة أخرى',
        ];
    }
}

