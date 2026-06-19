<?php
declare(strict_types=1);

namespace App\Controllers\Cron;

use App\Controllers\BaseController;
use App\Libraries\AiClient;
use App\Libraries\DocxService;
use App\Models\AiJobTypeModel;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

/**
 * AiJobsRunner: يُشغَّل عبر كرون
 * HTTP: GET /cron/ai-jobs/run?key=SECRET[&debug=1]
 * CLI : استدعِه من Command ومرّر $key و $debug باراميترات
 */
class AiJobsRunner extends BaseController
{
    use ResponseTrait;

    /**
     * إخراج JSON آمن للويب والـ CLI.
     */
    protected function out(array $data, int $status = 200)
    {
        if (is_cli()) {
            // CLI: أطبع JSON وأعده للـ Command
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return $data;
        }

        // WEB: أعد استجابة JSON نظامية
        if (isset($this->response)) {
            return $this->response->setStatusCode($status)->setJSON($data);
        }

        // احتياط نادر
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $data;
    }

    /**
     * دعم الاستدعاء من الويب والـ CLI:
     * - عند الويب نقرأ key/debug من GET.
     * - عند CLI نأخذهما من باراميترات الدالة.
     */
    public function run(?string $keyOverride = null, ?int $debugOverride = null)
    {
        // 🔐 قراءة المفتاح
        $providedKey = $keyOverride;
        if ($providedKey === null && isset($this->request) && method_exists($this->request, 'getGet')) {
            $providedKey = (string) ($this->request->getGet('key') ?? '');
        }
        $secret = (string) (getenv('CRON_SECRET') ?: '');
        if ($providedKey === '' || $secret === '' || $providedKey !== $secret) {
            return $this->out(['ok' => false, 'error' => 'Invalid cron key.'], 403);
        }

        // 🧪 وضع التصحيح
        $debug = $debugOverride;
        if ($debug === null && isset($this->request) && method_exists($this->request, 'getGet')) {
            $debug = (int) ($this->request->getGet('debug') ?? 0);
        }
        $debug = ((int) $debug === 1) ? 1 : 0;

        // ⏳ إعدادات زمن التنفيذ
        @ini_set('max_execution_time', '120');
        @set_time_limit(120);
        ignore_user_abort(true);

        // ♻️ إعادة اصطفاف أي processing طال وقتها (تحسبًا لانقطاع سابق) + حدّ أقصى للمحاولات
        $this->requeueStaleProcessing(5, (int) (getenv('AI_MAX_RETRY') ?: 5));

        $db = Database::connect();

        // 🧺 جلب دفعة صغيرة: نلتقط كلًّا من pending و queued
        // ترتيب حسب عدد المحاولات (الأقل أولاً) ثم حسب التاريخ
        $jobs = $db->table('ai_jobs')
            ->select('*')
            ->groupStart()
                ->where('status', 'pending')
                ->orWhere('status', 'queued')
            ->groupEnd()
            ->orderBy('retry_count', 'ASC')  // المهام الأقل محاولات أولاً
            ->orderBy('created_at', 'ASC')   // ثم الترتيب حسب التاريخ
            ->limit(1)
            ->get()->getResultArray();

        if (empty($jobs)) {
            return $this->out(['ok' => true, 'count' => 0, 'message' => 'No pending jobs.']);
        }

        // 🔒 قفلها إلى processing
        $ids = array_column($jobs, 'id');
        $db->table('ai_jobs')
            ->whereIn('id', $ids)
            ->set('status', 'processing')
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->update();

        $typeModel = new AiJobTypeModel();
        $aiClient  = new AiClient();
        $docx      = new DocxService();

        $processed = 0;
        $failed    = 0;
        $details   = [];

        foreach ($jobs as $job) {
            $info = ['job_id' => (int) $job['id']];
            try {
                $t0 = microtime(true);
                $this->processOneJob($job, $typeModel, $aiClient, $docx);
                $info['status'] = 'done';
                $info['ms']     = (int) round((microtime(true) - $t0) * 1000);
                $processed++;
            } catch (\Throwable $e) {
                $info['status'] = 'failed';
                $info['error']  = mb_substr($e->getMessage(), 0, 400);

                // إنعاش الاتصال قبل الكتابة
                $db = $this->ensureDbAlive($db);
                $currentRetryCount = (int)($job['retry_count'] ?? 0);
                
                // التحقق من خصم النقاط وإرجاعها عند الفشل
                $pointsDeducted = (int)($job['points_deducted'] ?? 0);
                if ($pointsDeducted === 1) {
                    $accessService = new \App\Services\AiTools\AiToolAccessService();
                    $accessService->refundPoints((int)$job['id']);
                    
                    // تحديث points_deducted بعد الإرجاع
                    $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
                        'points_deducted' => 0,
                    ]);
                }
                
                $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
                    'status'        => 'failed',
                    'error_message' => $info['error'],
                    'retry_count'   => $currentRetryCount + 1, // زيادة عداد المحاولات عند الفشل
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);

                $failed++;
            }
            $details[] = $info;
        }

        return $this->out(
            $debug
                ? ['ok' => true, 'processed' => $processed, 'failed' => $failed, 'details' => $details]
                : ['ok' => true, 'processed' => $processed, 'failed' => $failed]
        );
    }

    /**
     * يعيد بناء ملف DOCX لمهمة مكتملة مسبقاً باستخدام نتيجة الذكاء الاصطناعي المخزنة (result_json)
     * لتجاوز مشاكل تلف الملف دون الحاجة لاستدعاء الذكاء الاصطناعي من جديد.
     */
    public function rebuildFileForJob(int $jobId): void
    {
        $db = Database::connect();
        $job = $db->table('ai_jobs')->where('id', $jobId)->get()->getRowArray();
        if (!$job || empty($job['result_json']) || $job['status'] !== 'done') {
            throw new \RuntimeException('المهمة غير مكتملة أو لا تحتوي على نتيجة محفوظة.');
        }

        $typeModel = new AiJobTypeModel();
        $jobType = $typeModel->find((int) $job['job_type_id']);
        if (!$jobType) {
            throw new \RuntimeException('نوع المهمة غير موجود.');
        }

        $typeKey = (string) ($jobType['type_key'] ?? '');
        $payload = json_decode((string) $job['payload_json'], true) ?: [];
        $jsonObj = json_decode((string) $job['result_json'], true) ?: [];

        $buildDocx = (getenv('AI_BUILD_DOCX') === '1') && !empty($jobType['template_path']);
        if (!$buildDocx) {
            throw new \RuntimeException('هذا النوع من المهام لا يُنشئ ملفات.');
        }

        $docx = new DocxService();

        // 🎯 معالجة خاصة لأدوات تولّد ملفات خاصة بدون اعتماد template_path
        if ($typeKey === 'test_design_v1') {
            $this->processTestDesignJob($job, $jsonObj, $db);
            return;
        }

        // تجهيز بيانات القالب DOCX
        if ($typeKey === 'article_evaluator_v1') {
            global $_article_evaluator_payload;
            $_article_evaluator_payload = $payload;
        }
        if ($typeKey === 'formative_reflective_essay_v1') {
            global $_formative_essay_payload;
            $_formative_essay_payload = $payload;
        }
        $tplData = $this->makeTemplateDataForDocx($jsonObj, $typeKey);

        // تحديد المسارات
        $templateRel = ltrim((string) $jobType['template_path'], '/');
        
        // تسمية الملف حسب نوع الأداة
        if ($typeKey === 'analytical_article_v1') {
            $fileName = 'analytical_article_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/analytical_articles/' . $fileName;
        } elseif ($typeKey === 'case_study_v1') {
            $fileName = 'case_study_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/case_studies/' . $fileName;
        } elseif ($typeKey === 'case_integration_v1') {
            $fileName = 'Integrating_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/case_integrations/' . $fileName;
        } elseif ($typeKey === 'article_evaluator_v1') {
            $studentNumber = $payload['student_number'] ?? (int) $job['user_id'];
            $teacherSupervisor = $payload['teacher_supervisor'] ?? '';
            $supervisorCode = ($teacherSupervisor === 'رزان خليفة') ? 'RK' : 'AA';
            $fileName = $studentNumber . '_PLT_PP_FB_' . $supervisorCode . '.docx';
            $targetRel = 'downloads/article_feedback/' . $fileName;
        } elseif ($typeKey === 'evaluation_integration_v1') {
            $fileName = 'evaluation_integration_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/evaluation_integrations/' . $fileName;
        } elseif ($typeKey === 'assessment_position_v1') {
            $fileName = 'assessment_position_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/assessment_positions/' . $fileName;
        } elseif ($typeKey === 'question_develop_v1') {
            $fileName = 'question_develop_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/question_developments/' . $fileName;
        } elseif ($typeKey === 'inquiry_research_v1') {
            $fileName = 'inquiry_research_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_research/' . $fileName;
        } elseif ($typeKey === 'inquiry_report_evaluator_v1') {
            // يُعالج في processInquiryReportEvaluatorJob — هذا fallback فقط
            $studentId = $payload['student_id'] ?? (int) $job['user_id'];
            $fileName = $studentId . '_Inquiry_SF_AA.docx';
            $targetRel = 'downloads/inquiry_evaluations/' . $fileName;
        } elseif ($typeKey === 'inquiry_data_tools_v1') {
            $fileName = 'data_tools_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'inquiry_academic_writer_v1') {
            $fileName = 'academic_writer_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'inquiry_templates_v1') {
            $fileName = 'administrative_forms_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'bilingual_lesson_plan_v1') {
            $lang = $payload['language'] ?? $payload['Language'] ?? $payload['لغة'] ?? 'ar';
            if ($lang === 'en') {
                $templateRel = 'templates/bilingual_plan_en.docx';
            } else {
                $templateRel = 'templates/bilingual_plan_ar.docx';
            }
            $fileName = 'bilingual_lesson_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/lesson_plans/' . $fileName;
        } elseif ($typeKey === 'stakeholder_integration_v1') {
            $fileName = 'stakeholder_integration_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/stakeholder_integrations/' . $fileName;
        } elseif ($typeKey === 'formative_reflective_essay_v1') {
            $fileName = 'formative_essay_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/formative_essays/' . $fileName;
        } elseif ($typeKey === 'rewritten_reflective_essay_v1') {
            $fileName = 'rewritten_essay_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/formative_essays/' . $fileName;
        } else {
            $fileName = 'lesson_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/lesson_plans/' . $fileName;
        }

        $absTargetDir = rtrim(FCPATH, '/') . '/' . dirname($targetRel);
        if (!is_dir($absTargetDir)) {
            @mkdir($absTargetDir, 0775, true);
        }

        $filenameMeta = [];
        if ($typeKey === 'case_study_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'grade_level' => $tplData['grade_level'] ?? '',
                'case_title' => $tplData['case_title'] ?? '',
            ];
        } elseif ($typeKey === 'case_integration_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'case' => $tplData['case'] ?? '',
            ];
        } elseif ($typeKey === 'article_evaluator_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'student_number' => $tplData['student_number'] ?? '',
                'teacher_supervisor' => $tplData['teacher_supervisor'] ?? '',
            ];
        } elseif ($typeKey === 'evaluation_integration_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'subject_name' => $tplData['subject_name'] ?? '',
                'lesson_title' => $tplData['lesson_title'] ?? '',
            ];
        } elseif ($typeKey === 'question_develop_v1') {
            $filenameMeta = []; // No extra meta needed
        } else {
            $filenameMeta = [
                'teacher_name'  => $tplData['teacher_name'] ?? '',
                'school_class'  => $tplData['school_class'] ?? '',
                'general_title' => $tplData['general_title'] ?? '',
            ];
        }
        
        // استخدام القالب المحدد بدلاً من القالب المخزن في قاعدة البيانات للأداة ثنائية اللغة
        if ($typeKey !== 'bilingual_lesson_plan_v1') {
            $templateRel = ltrim((string) $jobType['template_path'], '/');
        }
        
        $built = $docx->buildFromTemplate($templateRel, $tplData, $targetRel, $filenameMeta, $typeKey);
    
        $absPath = rtrim(FCPATH, '/') . '/' . $built['rel_path'];
        if (!is_file($absPath)) {
            throw new \RuntimeException('فشل بناء الملف.');
        }

        // حذف الملف القديم إن وُجد
        if (!empty($job['file_id'])) {
            $oldFile = $db->table('files')->where('id', (int)$job['file_id'])->get()->getRowArray();
            if ($oldFile && !empty($oldFile['file_path'])) {
                $oldAbsPath = rtrim(FCPATH, '/') . '/' . ltrim($oldFile['file_path'], '/');
                if (is_file($oldAbsPath)) {
                    @unlink($oldAbsPath);
                }
            }
            // حذف السجلات المرتبطة أولاً لتجنب خطأ Foreign Key Constraint
            $db->table('user_task_files')->where('file_id', (int)$job['file_id'])->delete();
            $db->table('task_files')->where('file_id', (int)$job['file_id'])->delete();
            // ثم حذف الملف نفسه
            $db->table('files')->where('id', (int)$job['file_id'])->delete();
        }

        // تسجيل الملف الجديد
        $db = $this->ensureDbAlive($db);
        $fileId = $this->insertFileRowSmart($built['rel_path'], (int) $job['user_id'], (int) $built['bytes']);
        $this->linkTaskFileSafe((int) $job['task_id'], $fileId);
    
        $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
            'file_id'     => $fileId,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    protected function processOneJob(array $job, AiJobTypeModel $typeModel, AiClient $aiClient, DocxService $docx): void
    {
        $db = Database::connect();
    
        // 1️⃣ استرجاع نوع المهمة من جدول ai_job_types
        $jobType = $typeModel->find((int) $job['job_type_id']);
        if (!$jobType || (int) $jobType['is_enabled'] !== 1) {
            throw new \RuntimeException('Job type not found or disabled.');
        }
    
        // 2️⃣ استرجاع حمولة الإدخال (payload_json)
        $payload = json_decode((string) $job['payload_json'], true) ?: [];
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid payload_json.');
        }
    
        // 3️⃣ التوجيه الأساسي (system_prompt) من قاعدة البيانات
        $systemPrompt = (string) $jobType['system_prompt'];
        if ($systemPrompt === '') {
            throw new \RuntimeException('Missing system_prompt on job type.');
        }
    
        // 4️⃣ تهيئة تتبّع المعالجة وتعيين المزوّد والموديل من قاعدة البيانات
        $aiClient->withTrace('job#' . (int) $job['id']);
        $aiClient->withProviderModel(
            $jobType['provider']    ?? null,   // مثل gemini أو openai
            $jobType['model_name']  ?? null    // مثل gemini-2.0-flash أو gpt-4o-mini
        );
    
        // 5️⃣ التأكد أن type_key موجود في الحمولة (ليتعرف AiClient على نوع المهمة)
        if (empty($payload['type_key']) && !empty($jobType['type_key'])) {
            $payload['type_key'] = (string) $jobType['type_key'];
        }
        
        // 6️⃣ استدعاء الذكاء الاصطناعي — قد يرمي استثناء عند فشل الاتصال أو JSON غير صالح
        $typeKey = (string) ($jobType['type_key'] ?? '');
        if (empty($typeKey)) {
            throw new \RuntimeException('Missing type_key on job type.');
        }
        
        // 📊 Logging قبل استدعاء AI للتشخيص
        $provider = (string) ($jobType['provider'] ?? 'gemini');
        $model = (string) ($jobType['model_name'] ?? 'unknown');
        $promptLength = strlen($systemPrompt) + strlen(json_encode($payload, JSON_UNESCAPED_UNICODE));
        log_message('info', "AI Job #{$job['id']}: Starting AI call. Type: {$typeKey}, Provider: {$provider}, Model: {$model}, Prompt length: {$promptLength} chars");
        
        $t0 = microtime(true);
        try {
            $jsonText = $aiClient->generateJson($systemPrompt, $payload, $typeKey);
            $t1 = microtime(true);
            $elapsed = round($t1 - $t0, 2);
            log_message('info', "AI Job #{$job['id']}: AI call completed successfully in {$elapsed}s. Response length: " . strlen($jsonText) . " chars");
        } catch (\Throwable $e) {
            $t1 = microtime(true);
            $elapsed = round($t1 - $t0, 2);
            log_message('error', "AI Job #{$job['id']}: AI call failed after {$elapsed}s - " . $e->getMessage());
            // حفظ request_dump حتى لو فشل الطلب
            if ($this->columnExists('ai_jobs', 'request_dump')) {
                $db = $this->ensureDbAlive($db);
                $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
                    'request_dump' => json_encode([
                        'provider'      => $aiClient->getProvider(),
                        'model'         => $aiClient->getModel(),
                        'trace'         => $aiClient->getTraceId(),
                        'merged_prompt' => $aiClient->getLastMergedPrompt(),
                        'request_body'  => $aiClient->getLastRequestBody(),
                        'http'          => $aiClient->getLastHttpInfo(),
                        'error'         => $e->getMessage(),
                        'elapsed_seconds' => $elapsed,
                        'ts'            => date('c'),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            throw $e;
        }
        
        $jsonObj  = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
    
        // ✅ تأكد أن الاتصال حي قبل أي كتابة
        $db = $this->ensureDbAlive($db);
    
        // 7️⃣ حفظ اللوج الكامل إن وُجد عمود request_dump لأغراض التشخيص
        if ($this->columnExists('ai_jobs', 'request_dump')) {
            // حفظ response الكامل أيضاً (قد يحتوي على معلومات RAG sources)
            $httpInfo = $aiClient->getLastHttpInfo();
            $responseRaw = $aiClient->getLastResponseRaw(); // يعيد JSON string أو null
            
            $responseData = [
                'provider'      => $aiClient->getProvider(),
                'model'         => $aiClient->getModel(),
                'trace'         => $aiClient->getTraceId(),
                'merged_prompt' => $aiClient->getLastMergedPrompt(),
                'request_body'  => $aiClient->getLastRequestBody(),
                'http'          => $httpInfo,
                'response_raw'  => $responseRaw ? json_decode($responseRaw, true) : null, // حفظ response الكامل كمصفوفة (قد يحتوي على RAG metadata في candidates[].groundingMetadata)
                'ts'            => date('c'),
            ];
            
            $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
                'request_dump' => json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    
        // 🎯 معالجة خاصة لأدوات تولّد ملفات خاصة بدون اعتماد template_path
        if ($typeKey === 'test_design_v1') {
            $this->processTestDesignJob($job, $jsonObj, $db);
            return;
        }

        // 🎯 معالجة خاصة لمقيّم البحث الاستقصائي (ملفان: تغذية راجعة + روبريك)
        if ($typeKey === 'inquiry_report_evaluator_v1') {
            $this->processInquiryReportEvaluatorJob($job, $jsonObj, $payload, $db);
            return;
        }

        // 8️⃣ إن لم يكن مطلوب بناء ملف DOCX نكتفي بحفظ الناتج JSON
        $buildDocx = (getenv('AI_BUILD_DOCX') === '1') && !empty($jobType['template_path']);
        if (!$buildDocx) {
            $db = $this->ensureDbAlive($db);
            $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
                'result_json' => json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'      => 'done',
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            // 8️⃣-أ: إذا كان رد على تعليق، حدّث forum_reply_comments
            if (!empty($payload['is_comment_reply']) && $this->columnExists('forum_reply_comments', 'ai_job_id')) {
                $db->table('forum_reply_comments')
                   ->where('ai_job_id', (int) $job['id'])
                   ->update([
                       'reply_result' => json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                       'status'      => 'done',
                       'updated_at'  => date('Y-m-d H:i:s'),
                   ]);
            }

            return;
        }
    

        // 9️⃣ تجهيز بيانات القالب DOCX من المخرجات JSON (تمرير type_key لتمييز نوع الأداة)
        // لمقيم المقالات: تمرير payload عبر global لاستخدامه في makeTemplateDataForDocx
        if ($typeKey === 'article_evaluator_v1') {
            global $_article_evaluator_payload;
            $_article_evaluator_payload = $payload;
        }
        if ($typeKey === 'formative_reflective_essay_v1') {
            global $_formative_essay_payload;
            $_formative_essay_payload = $payload;
        }
        $tplData = $this->makeTemplateDataForDocx($jsonObj, $typeKey);
    
        // 🔟 تحديد المسارات
        $templateRel = ltrim((string) $jobType['template_path'], '/');
        
        // تسمية الملف حسب نوع الأداة
        if ($typeKey === 'analytical_article_v1') {
            $fileName = 'analytical_article_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/analytical_articles/' . $fileName;
        } elseif ($typeKey === 'case_study_v1') {
            $fileName = 'case_study_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/case_studies/' . $fileName;
        } elseif ($typeKey === 'case_integration_v1') {
            $fileName = 'Integrating_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/case_integrations/' . $fileName;
        } elseif ($typeKey === 'article_evaluator_v1') {
            // تحديد اسم الملف حسب student_number و teacher_supervisor
            $studentNumber = $payload['student_number'] ?? (int) $job['user_id'];
            $teacherSupervisor = $payload['teacher_supervisor'] ?? '';
            $supervisorCode = ($teacherSupervisor === 'رزان خليفة') ? 'RK' : 'AA';
            $fileName = $studentNumber . '_PLT_PP_FB_' . $supervisorCode . '.docx';
            $targetRel = 'downloads/article_feedback/' . $fileName;
        } elseif ($typeKey === 'evaluation_integration_v1') {
            $fileName = 'evaluation_integration_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/evaluation_integrations/' . $fileName;
        } elseif ($typeKey === 'assessment_position_v1') {
            $fileName = 'assessment_position_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/assessment_positions/' . $fileName;
        } elseif ($typeKey === 'question_develop_v1') {
            $fileName = 'question_develop_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/question_developments/' . $fileName;
        } elseif ($typeKey === 'inquiry_research_v1') {
            $fileName = 'inquiry_research_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_research/' . $fileName;
        } elseif ($typeKey === 'inquiry_report_evaluator_v1') {
            // يُعالج في processInquiryReportEvaluatorJob — هذا fallback فقط
            $studentId = $payload['student_id'] ?? (int) $job['user_id'];
            $fileName = $studentId . '_Inquiry_SF_AA.docx';
            $targetRel = 'downloads/inquiry_evaluations/' . $fileName;
        } elseif ($typeKey === 'inquiry_data_tools_v1') {
            $fileName = 'data_tools_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'inquiry_academic_writer_v1') {
            $fileName = 'academic_writer_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'inquiry_templates_v1') {
            $fileName = 'administrative_forms_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/inquiry_tools/' . $fileName;
        } elseif ($typeKey === 'bilingual_lesson_plan_v1') {
            $lang = $payload['language'] ?? $payload['Language'] ?? $payload['لغة'] ?? 'ar';
            if ($lang === 'en') {
                $templateRel = 'templates/bilingual_plan_en.docx';
            } else {
                $templateRel = 'templates/bilingual_plan_ar.docx';
            }
            $fileName = 'bilingual_lesson_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/lesson_plans/' . $fileName;
        } elseif ($typeKey === 'stakeholder_integration_v1') {
            $fileName = 'stakeholder_integration_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/stakeholder_integrations/' . $fileName;
        } elseif ($typeKey === 'formative_reflective_essay_v1') {
            $fileName = 'formative_essay_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/formative_essays/' . $fileName;
        } elseif ($typeKey === 'rewritten_reflective_essay_v1') {
            $fileName = 'rewritten_essay_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/formative_essays/' . $fileName;
        } else {
            $fileName = 'lesson_' . (int) $job['user_id'] . '_' . (int) $job['task_id'] . '_' . date('Ymd_His') . '.docx';
            $targetRel = 'downloads/lesson_plans/' . $fileName;
        }
    
        // إنشاء مجلد الوجهة إذا لم يكن موجودًا
        $absTargetDir = rtrim(FCPATH, '/') . '/' . dirname($targetRel);
        if (!is_dir($absTargetDir)) {
            @mkdir($absTargetDir, 0775, true);
        }
    
        // 1️⃣1️⃣ بناء الملف باستخدام DocxService (تمرير type_key لتمييز نوع الأداة)
        $filenameMeta = [];
        if ($typeKey === 'case_study_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'grade_level' => $tplData['grade_level'] ?? '',
                'case_title' => $tplData['case_title'] ?? '',
            ];
        } elseif ($typeKey === 'case_integration_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'case' => $tplData['case'] ?? '',
            ];
        } elseif ($typeKey === 'article_evaluator_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'student_number' => $tplData['student_number'] ?? '',
                'teacher_supervisor' => $tplData['teacher_supervisor'] ?? '',
            ];
        } elseif ($typeKey === 'evaluation_integration_v1') {
            $filenameMeta = [
                'teacher_name' => $tplData['teacher_name'] ?? '',
                'subject_name' => $tplData['subject_name'] ?? '',
                'lesson_title' => $tplData['lesson_title'] ?? '',
            ];
        } elseif ($typeKey === 'question_develop_v1') {
            $filenameMeta = []; // No extra meta needed
        } else {
            $filenameMeta = [
                'teacher_name'  => $tplData['teacher_name'] ?? '',
                'school_class'  => $tplData['school_class'] ?? '',
                'general_title' => $tplData['general_title'] ?? '',
            ];
        }
        
        // استخدام القالب المحدد بدلاً من القالب المخزن في قاعدة البيانات للأداة ثنائية اللغة
        if ($typeKey !== 'bilingual_lesson_plan_v1') {
            $templateRel = ltrim((string) $jobType['template_path'], '/');
        }
        
        $built = $docx->buildFromTemplate($templateRel, $tplData, $targetRel, $filenameMeta, $typeKey);
    
        $absPath = rtrim(FCPATH, '/') . '/' . $built['rel_path'];
        if (!is_file($absPath)) {
            throw new \RuntimeException('DOCX build failed or file missing after build.');
        }
    
        // 1️⃣2️⃣ تسجيل الملف الجديد في جدول files والربط بالـ task_files
        $db = $this->ensureDbAlive($db);
        $fileId = $this->insertFileRowSmart($built['rel_path'], (int) $job['user_id'], (int) $built['bytes']);
        $this->linkTaskFileSafe((int) $job['task_id'], $fileId);
    
        // 1️⃣3️⃣ تحديث سجل المهمة بالنتيجة والملف النهائي
        $db = $this->ensureDbAlive($db);
        $db->table('ai_jobs')->where('id', (int) $job['id'])->update([
            'result_json' => json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'file_id'     => $fileId,
            'status'      => 'done',
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // 1️⃣3️⃣-أ: إذا كان رد على تعليق، حدّث forum_reply_comments
        if (!empty($payload['is_comment_reply']) && $this->columnExists('forum_reply_comments', 'ai_job_id')) {
            $db->table('forum_reply_comments')
               ->where('ai_job_id', (int) $job['id'])
               ->update([
                   'reply_result' => json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                   'status'      => 'done',
                   'updated_at'  => date('Y-m-d H:i:s'),
               ]);
        }
    }


    /**
     * إعادة اصطفاف أي processing أقدم من N دقائق (انقطعت سابقًا) مع حد أقصى للمحاولات.
     */
    protected function requeueStaleProcessing(int $olderThanMinutes = 5, int $maxRetries = 5): void
    {
        $db = Database::connect();
        $db->table('ai_jobs')
            ->set('status', 'queued')
            ->set('retry_count', 'retry_count + 1', false)
            ->set('error_message', 'Runner timeout or interrupted. Re-queued.')
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('status', 'processing')
            ->where('retry_count <', $maxRetries)
            ->where('updated_at <', date('Y-m-d H:i:s', time() - ($olderThanMinutes * 60)))
            ->update();
    }

    /**
     * إدراج ذكي في files يراعي اختلاف الأعمدة (uploader_id / uploaded_by، uploaded_at / created_at، file_size)
     */
    public function insertFileRowSmart(string $relPath, int $userId, int $bytes): int
    {
        $db = Database::connect();

        $fields = array_map('strtolower', $db->getFieldNames('files')); // أعمدة جدول files (lowercase)

        $data = [
            'file_name' => basename($relPath),
            'file_path' => $relPath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        // ✅ مُرسل الملف: uploader_id أو uploaded_by
        if (in_array('uploader_id', $fields, true)) {
            $data['uploader_id'] = $userId;
        } elseif (in_array('uploaded_by', $fields, true)) {
            $data['uploaded_by'] = $userId;
        }

        // ✅ الطابع الزمني: uploaded_at أو created_at
        $now = date('Y-m-d H:i:s');
        if (in_array('uploaded_at', $fields, true)) {
            $data['uploaded_at'] = $now;
        } elseif (in_array('created_at', $fields, true)) {
            $data['created_at'] = $now;
        }

        // ✅ حجم الملف إن وُجد العمود
        if (in_array('file_size', $fields, true)) {
            $data['file_size'] = $bytes;
        }

        $db->table('files')->insert($data);
        $id = (int) $db->insertID();
        if ($id <= 0) {
            throw new \RuntimeException('files insert failed (no insertID).');
        }
        return $id;
    }

    /**
     * ربط آمن: لا يرمي استثناء عند فشل FK (يُستخدم لاحقًا بعد ضبط المخطط).
     */
    public function linkTaskFileSafe(int $taskId, int $fileId): void
    {
        $db = Database::connect();
        $db = $this->ensureDbAlive($db);
        try {
            $db->table('user_task_files')->insert([
                'task_id'       => $taskId,
                'file_id'       => $fileId,
            ]);
        } catch (\Throwable $e) {
            // تجاهُل الربط إن كان FK غير جاهز، دون إفشال الطلب.
        }
    }

    /**
     * فحص وجود عمود ضمن جدول (لاستخدامه بشكل شرطي).
     */
    protected function columnExists(string $table, string $column): bool
    {
        try {
            $db = Database::connect();
            $db = $this->ensureDbAlive($db);
            $fields = array_map('strtolower', $db->getFieldNames($table));
            return in_array(strtolower($column), $fields, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * إن مات الاتصال (wait_timeout / gone away) نحاول إنعاشه بلُطف.
     */
    protected function ensureDbAlive($db)
    {
        try {
            // لو ما في اتصال أصلاً
            if (!$db->connID) {
                $db->reconnect();
                return $db;
            }
            // نحاول عمل query بسيط وإن فشل نعيد الاتصال
            try {
                if (is_object($db->connID) && method_exists($db->connID, 'query')) {
                    if (!@$db->connID->query("SELECT 1")) {
                        $db->reconnect();
                    }
                } else {
                    $db->reconnect();
                }
            } catch (\Throwable $e) {
                $db->reconnect();
            }
            return $db;
        } catch (\Throwable $e) {
            try { $db->close(); } catch (\Throwable $e2) {}
            return Database::connect();
        }
    }

    // =======================
    // 🧩 تهيئة بيانات القالب
    // =======================
    /**
     * تجهيز البيانات لتتوافق مع البليس هولدر في القالب.
     * - يحوّل مصفوفات knowledge_* و learning_sources إلى نص متعدد الأسطر.
     * - يبني outcome_rows كمصفوفة صفوف {o_*} للبلوك {#outcome_rows}…{/outcome_rows} (لخطة الحصة فقط).
     * 
     * @param array $j البيانات JSON من AI
     * @param string|null $typeKey نوع الأداة (matrix_analysis_v1, lesson_plan_v1, etc.)
     */
    public function makeTemplateDataForDocx(array $j, ?string $typeKey = null): array
    {
        // تمييز نوع الأداة
        $isMatrixAnalysis    = ($typeKey === 'matrix_analysis_v1');
        $isAnalyticalArticle = ($typeKey === 'analytical_article_v1');
        $isCaseStudy         = ($typeKey === 'case_study_v1');
        $isArticleEvaluator  = ($typeKey === 'article_evaluator_v1');
        $isEvalIntegration   = ($typeKey === 'evaluation_integration_v1');
        $isInquiryResearch   = ($typeKey === 'inquiry_research_v1');
        $isBilingualLessonPlan = ($typeKey === 'bilingual_lesson_plan_v1');
        
        // مُحوّل عام للنص
        $textify = function ($v, string $sep = '، ') {
            if (is_array($v)) {
                $v = array_map(fn($x) => is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE), $v);
                return implode($sep, $v);
            }
            return is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
        };

        // نبدأ بنسخة من الأصل
        $out = $j;

        // ✅ للبحث الاستقصائي: نُمرّر البيانات مباشرة، DocxService يتولى كل المعالجة
        if ($isInquiryResearch) {
            // section_3_table تأتي بالفعل كمصفوفة من JSON — لا تحتاج تحويل
            return $out;
        }

        // ✅ لمقيّم البحث الاستقصائي: تجهيز بيانات القالبين (تغذية راجعة + روبريك)
        $isInquiryReportEvaluator = ($typeKey === 'inquiry_report_evaluator_v1');
        if ($isInquiryReportEvaluator) {
            // بيانات الطالب من payload (تُمرر عبر global)
            global $_inquiry_report_evaluator_payload;
            if (isset($_inquiry_report_evaluator_payload)) {
                $out['student_name'] = $textify($_inquiry_report_evaluator_payload['student_name'] ?? '');
                $out['student_id'] = $textify($_inquiry_report_evaluator_payload['student_id'] ?? '');
                $out['teacher_supervisor'] = $textify($_inquiry_report_evaluator_payload['teacher_supervisor'] ?? '');
            }
            $out['date'] = date('Y-m-d');

            // التقدير الكلي
            $overall = $j['overall_evaluation'] ?? [];
            $out['overall_grade_symbol'] = $textify($overall['grade_symbol'] ?? '');
            $out['overall_grade_word'] = $textify($overall['grade_word'] ?? '');

            // تقييمات الروبريك (8 معايير)
            $rubricGrades = $j['rubric_grades'] ?? [];
            foreach ($rubricGrades as $i => $grade) {
                $idx = ($grade['criterion_id'] ?? ($i + 1));
                $out["criterion_{$idx}_name"] = $textify($grade['criterion_name'] ?? '');
                $out["criterion_{$idx}_symbol"] = $textify($grade['grade_symbol'] ?? '');
                $out["criterion_{$idx}_word"] = $textify($grade['grade_word'] ?? '');
                // تحديد أي عمود يُظلَّل في الروبريك
                foreach (['D','M','GP','P','F'] as $level) {
                    $out["criterion_{$idx}_{$level}"] = ($grade['grade_symbol'] === $level) ? '✓' : '';
                }
            }

            // نقاط القوة
            $strengths = $j['feedback_report']['strengths'] ?? [];
            $out['strengths'] = [];
            foreach ($strengths as $s) {
                $text = $textify($s);
                if (!empty(trim($text))) {
                    $out['strengths'][] = ['text' => $text];
                }
            }

            // مجالات التطوير
            $developments = $j['feedback_report']['areas_for_development'] ?? [];
            $out['areas_for_development'] = [];
            foreach ($developments as $d) {
                $text = $textify($d);
                if (!empty(trim($text))) {
                    $out['areas_for_development'][] = ['text' => $text];
                }
            }

            // ملاحظات عامة
            $out['general_notes'] = $textify($j['feedback_report']['general_notes'] ?? '');

            return $out;
        }

        // ✅ لأدوات البحث الاستقصائي الجديدة (التي تستخدم قوالب فارغة)
        $isInquiryTools = in_array($typeKey, ['inquiry_data_tools_v1', 'inquiry_academic_writer_v1', 'inquiry_templates_v1']);
        if ($isInquiryTools) {
            $out['result'] = $textify($j['content'] ?? '');
            $out['title']  = $textify($j['title'] ?? '');
            return $out;
        }

        // ✅ لأداة التخطيط ثنائية اللغة:
        if ($isBilingualLessonPlan) {
            foreach (['subject', 'teacher_gender', 'student_gender', 'grade_level', 'unit_title', 'lesson_title', 'classes_count', 'previous_learning', 'main_outcomes', 'teaching_strategies', 'evaluation_strategies', 'general_description'] as $k) {
                $out[$k] = $textify($j[$k] ?? '');
            }
            if (!empty($j['outcome_rows']) && is_array($j['outcome_rows'])) {
                $i = 1;
                foreach ($j['outcome_rows'] as $r) {
                    $out["time_$i"] = $textify($r['time'] ?? '');
                    $out["learner_action_$i"] = $textify($r['learner_action'] ?? '');
                    $out["teacher_action_$i"] = $textify($r['teacher_action'] ?? '');
                    $i++;
                    if ($i > 4) break; // لدينا 4 مراحل فقط
                }
            }
            return $out;
        }

        // ✅ للمقال التحليلي: نحول الحقول مباشرة (استخدام أسماء إنجليزية للقالب)
        if ($isAnalyticalArticle) {
            // العنوان
            $out['article_title'] = $textify($j['عنوان المقال'] ?? '');
            
            // المقدمة: فقرتان منفصلتان
            $out['introduction_paragraph_1'] = $textify($j['المقدمة_الفقرة_الأولى'] ?? '');
            $out['introduction_paragraph_2'] = $textify($j['المقدمة_الفقرة_الثانية'] ?? '');
            
            // العرض: 3 فقرات منفصلة
            $out['body_paragraph_1'] = $textify($j['العرض_الفقرة_الأولى'] ?? '');
            $out['body_paragraph_2'] = $textify($j['العرض_الفقرة_الثانية'] ?? '');
            $out['body_paragraph_3'] = $textify($j['العرض_الفقرة_الثالثة'] ?? '');
            
            // الخاتمة: فقرتان منفصلتان
            $out['conclusion_paragraph_1'] = $textify($j['الخاتمة_الفقرة_الأولى'] ?? '');
            $out['conclusion_paragraph_2'] = $textify($j['الخاتمة_الفقرة_الثانية'] ?? '');
            
            // المراجع: حفظ كـ array (سيتم التعامل معها في Template)
            $references = $j['المراجع'] ?? [];
            if (is_array($references)) {
                $out['references'] = $references; // حفظ كـ array مباشرة
            } else {
                $out['references'] = [];
            }
            
            return $out;
        }

        // ✅ لمصفوفة التحليل: نحول فقط الحقول الأساسية والمصفوفة
        if ($isMatrixAnalysis) {
            // الحقول الأساسية (نص مباشر)
            foreach (['teacher_name', 'stage_level', 'subject_name', 'unit_title', 'curriculum_pages', 'lessons_count'] as $k) {
                $out[$k] = $textify($j[$k] ?? '');
            }
            
            // حقول المصفوفة (نص مباشر)
            foreach (['matrix_col_content', 'matrix_col_concepts', 'matrix_col_principles', 
                      'matrix_col_activities', 'matrix_col_values', 'matrix_col_visuals', 'matrix_col_other_tasks'] as $k) {
                $out[$k] = $textify($j[$k] ?? '');
            }
            
            // ✅ لا نضيف outcome_rows لمصفوفة التحليل
            return $out;
        }

        // ✅ لدراسة الحالة: معالجة خاصة
        if ($isCaseStudy) {
            // الحقول الأساسية (نص مباشر)
            foreach (['teacher_name', 'grade_level', 'case_title', 'context', 'main_dilemma', 
                      'characters', 'case_identification', 'case_description', 'case_evaluation', 
                      'proposed_alternatives', 'teacher_criteria'] as $k) {
                $out[$k] = $textify($j[$k] ?? '');
            }
            
            return $out;
        }

        // ✅ لمقيم المقالات: معالجة خاصة
        if ($isArticleEvaluator) {
            // الحقول الأساسية (من payload، سيتم تمريرها من processOneJob)
            global $_article_evaluator_payload;
            if (isset($_article_evaluator_payload)) {
                $out['teacher_name'] = $textify($_article_evaluator_payload['teacher_name'] ?? '');
                $out['student_number'] = $textify($_article_evaluator_payload['student_number'] ?? '');
                $out['teacher_supervisor'] = $textify($_article_evaluator_payload['teacher_supervisor'] ?? '');
                
                // إضافة formatting_compliance
                $formattingData = $_article_evaluator_payload['formatting_compliance'] ?? [];
                if (is_array($formattingData)) {
                    $active = [];
                    $inactive = [];
                    foreach ($formattingData as $item => $value) {
                        if ($value == 1 || $value === true) {
                            $active[] = $item;
                        } else {
                            $inactive[] = $item;
                        }
                    }
                    $formattingText = '';
                    if (!empty($active)) {
                        $formattingText .= "ملتزم: " . implode('، ', $active);
                    }
                    if (!empty($inactive)) {
                        $formattingText .= ($formattingText ? ' | ' : '') . "غير ملتزم: " . implode('، ', $inactive);
                    }
                    $out['formatting_compliance'] = $textify($formattingText);
                } else {
                    $out['formatting_compliance'] = '';
                }
            }
            
            // استخراج general_guidance من JSON
            $out['general_guidance'] = $textify($j['general_guidance'] ?? '');
            
            // معالجة الأقسام (المقدمة، العرض، الخاتمة)
            $sections = ['introduction', 'body', 'conclusion'];
            foreach ($sections as $sec) {
                $sectionData = $j[$sec] ?? [];
                
                // التغذية الراجعة للقسم ككل
                $out["{$sec}_section_feedback"] = $textify($sectionData['section_feedback'] ?? '');
                
                // الأجزاء (parts) وتعليقاتها (feedback_items)
                $out["{$sec}_feedbacks"] = [];
                if (isset($sectionData['parts']) && is_array($sectionData['parts'])) {
                    foreach ($sectionData['parts'] as $part) {
                        $partTitle = $textify($part['part_title'] ?? '');
                        if (isset($part['feedback_items']) && is_array($part['feedback_items'])) {
                            foreach ($part['feedback_items'] as $item) {
                                $textAnchor = $textify($item['text_anchor'] ?? '');
                                $feedbackPara = $textify($item['feedback_paragraph'] ?? '');
                                
                                if (!empty(trim($feedbackPara))) {
                                    $combinedText = "{$partTitle}\nالنص المحدد: {$textAnchor}\nالتعليق: {$feedbackPara}";
                                    $out["{$sec}_feedbacks"][] = ['text' => $combinedText];
                                }
                            }
                        } elseif (isset($part['feedback_paragraph'])) {
                            // التوافق مع الهيكل القديم في حال دعت الحاجة
                            $feedbackPara = $textify($part['feedback_paragraph'] ?? '');
                            if (!empty(trim($feedbackPara))) {
                                $combinedText = "{$partTitle}\nالتعليق: {$feedbackPara}";
                                $out["{$sec}_feedbacks"][] = ['text' => $combinedText];
                            }
                        }
                    }
                }
            }
            
            // استخراج evaluation_summary وتحويله إلى مصفوفات
            $evaluationSummary = $j['evaluation_summary'] ?? [];
            
            // المعرفة والفهم والتطبيق
            $knowledgeData = $evaluationSummary['المعرفة والفهم والتطبيق'] ?? [];
            $out['knowledge_strengths'] = [];
            $out['knowledge_improvements'] = [];
            if (isset($knowledgeData['strengths']) && is_array($knowledgeData['strengths'])) {
                foreach ($knowledgeData['strengths'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['knowledge_strengths'][] = ['text' => $point];
                    }
                }
            }
            if (isset($knowledgeData['improvement_areas']) && is_array($knowledgeData['improvement_areas'])) {
                foreach ($knowledgeData['improvement_areas'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['knowledge_improvements'][] = ['text' => $point];
                    }
                }
            }
            
            // المهارات الإدراكية
            $cognitiveData = $evaluationSummary['المهارات الإدراكيّة'] ?? [];
            $out['cognitive_strengths'] = [];
            $out['cognitive_improvements'] = [];
            if (isset($cognitiveData['strengths']) && is_array($cognitiveData['strengths'])) {
                foreach ($cognitiveData['strengths'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['cognitive_strengths'][] = ['text' => $point];
                    }
                }
            }
            if (isset($cognitiveData['improvement_areas']) && is_array($cognitiveData['improvement_areas'])) {
                foreach ($cognitiveData['improvement_areas'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['cognitive_improvements'][] = ['text' => $point];
                    }
                }
            }
            
            // المهارات المهنية
            $professionalData = $evaluationSummary['المهارات المهنيّة'] ?? [];
            $out['professional_strengths'] = [];
            $out['professional_improvements'] = [];
            if (isset($professionalData['strengths']) && is_array($professionalData['strengths'])) {
                foreach ($professionalData['strengths'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['professional_strengths'][] = ['text' => $point];
                    }
                }
            }
            if (isset($professionalData['improvement_areas']) && is_array($professionalData['improvement_areas'])) {
                foreach ($professionalData['improvement_areas'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['professional_improvements'][] = ['text' => $point];
                    }
                }
            }
            
            // التقديم
            $presentationData = $evaluationSummary['التقديم'] ?? [];
            $out['presentation_strengths'] = [];
            $out['presentation_improvements'] = [];
            if (isset($presentationData['strengths']) && is_array($presentationData['strengths'])) {
                foreach ($presentationData['strengths'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['presentation_strengths'][] = ['text' => $point];
                    }
                }
            }
            if (isset($presentationData['improvement_areas']) && is_array($presentationData['improvement_areas'])) {
                foreach ($presentationData['improvement_areas'] as $item) {
                    $point = $textify($item['point'] ?? '');
                    // تصفية العناصر الفارغة
                    if (!empty(trim($point))) {
                        $out['presentation_improvements'][] = ['text' => $point];
                    }
                }
            }
            
            return $out;
        }

        // ✅ لدمج التقويم: معالجة خاصة
        if ($isEvalIntegration) {
            foreach ([
                'teacher_name', 'academic_number', 'subject_name', 'lesson_title',
                'cognitive_outcome', 'skill_outcome', 'cognitive_evaluation', 'skill_evaluation',
                'cognitive_teaching', 'skill_teaching',
                'reflection_satisfactory_deal', 'reflection_satisfactory_decision', 'reflection_satisfactory_adjustment',
                'reflection_unsatisfactory_deal', 'reflection_unsatisfactory_decision', 'reflection_unsatisfactory_adjustment',
                'reflection_neutral_deal', 'reflection_neutral_decision', 'reflection_neutral_adjustment'
            ] as $k) {
                $out[$k] = $textify($j[$k] ?? '');
            }
            
            // تحويل المؤشرات إلى قائمة نقطية
            foreach (['cognitive_indicators', 'skill_indicators'] as $k) {
                $arr = $j[$k] ?? [];
                if (is_array($arr)) {
                    $lines = array_map(
                        fn($x) => '- ' . (is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE)),
                        $arr
                    );
                    $out[$k] = implode("\n", $lines);
                } else {
                    $out[$k] = $textify($arr);
                }
            }
            return $out;
        }

        // ✅ لخطة الحصة: المعالجة الكاملة
        // الحقول النصية المباشرة المذكورة في الجداول 1–3
        foreach ([
            'teacher_name','school_name','general_subject','school_class','general_title','general_unit',
            'integration_vertical','integration_horizontal','prior_knowledge',
            'e_application',
            'warmup_teacher','warmup_student','intro_teacher','intro_student',
            'closure_teacher','closure_student','reflection',
        ] as $k) {
            $out[$k] = $textify($j[$k] ?? '');
        }

        // دقائق الأنشطة
        foreach (['warmup_minutes','intro_minutes','closure_minutes'] as $m) {
            $out[$m] = isset($j[$m]) ? (string)$j[$m] : '';
        }

        // حقول المعرفة/القيم/المصادر: إلى قائمة نقطية بأسطر متعددة
        foreach (['knowledge_cognitive','knowledge_skills','knowledge_values','learning_sources'] as $k) {
            $arr = $j[$k] ?? [];
            if (is_array($arr)) {
                $lines = array_map(
                    fn($x) => '• ' . (is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE)),
                    $arr
                );
                $out[$k] = implode("\n", $lines);
            } else {
                $out[$k] = $textify($arr);
            }
        }

        // ===== outcome_rows (لخطة الحصة فقط) =====
        $rows = [];
        $N = max(
            is_countable($j['o_statement'] ?? null) ? count($j['o_statement']) : 0,
            is_countable($j['o_teacher']  ?? null) ? count($j['o_teacher'])  : 0,
            is_countable($j['o_student']  ?? null) ? count($j['o_student'])  : 0,
            is_countable($j['o_duration'] ?? null) ? count($j['o_duration']) : 0
        );

        // مساعدات انتقاء
        $pick = function ($arr, int $i): string {
            if (!is_array($arr)) return is_scalar($arr) ? (string)$arr : '';
            return array_key_exists($i, $arr)
                ? (is_scalar($arr[$i]) ? (string)$arr[$i] : json_encode($arr[$i], JSON_UNESCAPED_UNICODE))
                : '';
        };
        $pickOrJoin = function ($arr, int $i): string {
            if (is_array($arr)) {
                return array_key_exists($i, $arr)
                    ? (is_scalar($arr[$i]) ? (string)$arr[$i] : json_encode($arr[$i], JSON_UNESCAPED_UNICODE))
                    : implode('، ', array_map(fn($x) => is_scalar($x) ? (string)$x : json_encode($x, JSON_UNESCAPED_UNICODE), $arr));
            }
            return is_scalar($arr) ? (string)$arr : '';
        };

        for ($i = 0; $i < $N; $i++) {
            $rows[] = [
                'o_statement'       => $pick($j['o_statement'] ?? [], $i),
                'o_assess_strategy' => $pickOrJoin($j['o_assess_strategy'] ?? [], $i),
                'o_assess_tool'     => $pickOrJoin($j['o_assess_tool'] ?? [], $i),
                'o_teach_strategy'  => $pickOrJoin($j['o_teach_strategy'] ?? [], $i),
                'o_teacher'         => $pick($j['o_teacher'] ?? [], $i),
                'o_student'         => $pick($j['o_student'] ?? [], $i),
                'o_duration'        => $pick($j['o_duration'] ?? [], $i),
            ];
        }
        $out['outcome_rows'] = $rows;

        return $out;
    }

    /**
     * ✅ معالجة أداة تصميم الاختبار: ينتج 3 ملفات ويخزّن معرّفاتها في result_json
     */
    protected function processTestDesignJob(array $job, array $jsonObj, $db): void
    {
        $docx    = new DocxService();
        $userId  = (int) $job['user_id'];
        $taskId  = (int) $job['task_id'];
        $ts      = date('Ymd_His');

        // ── الملف الأول: وورد تحليل المحتوى ──
        $rel1    = 'downloads/test_designs/analysis/test_analysis_' . $userId . '_' . $taskId . '_' . $ts . '.docx';
        $built1  = $docx->buildTestDesignWordAnalysis($jsonObj, $rel1);
        $abs1    = rtrim(FCPATH, '/') . '/' . $built1['rel_path'];
        if (!is_file($abs1)) {
            throw new \RuntimeException('Test design Word(1) not saved: ' . $abs1);
        }
        $fileId1 = $this->insertFileRowSmart($built1['rel_path'], $userId, (int)$built1['bytes']);
        $this->linkTaskFileSafe($taskId, $fileId1);

        // ── الملف الثاني: إكسيل جدول المواصفات (اختياري — يعتمد على PhpSpreadsheet) ──
        $fileId2 = 0;
        try {
            $rel2   = 'downloads/test_designs/excel/test_spec_' . $userId . '_' . $taskId . '_' . $ts . '.xlsx';
            $built2 = $docx->fillExcelTemplate($jsonObj, $rel2);
            $abs2   = rtrim(FCPATH, '/') . '/' . $built2['rel_path'];
            if (is_file($abs2)) {
                $db2    = $this->ensureDbAlive($db);
                $fields = array_map('strtolower', $db2->getFieldNames('files'));
                $xlData = [
                    'file_name' => basename($rel2),
                    'file_path' => $built2['rel_path'],
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
                if (in_array('uploader_id', $fields, true))       { $xlData['uploader_id'] = $userId; }
                elseif (in_array('uploaded_by', $fields, true))   { $xlData['uploaded_by'] = $userId; }
                $now = date('Y-m-d H:i:s');
                if (in_array('uploaded_at', $fields, true))       { $xlData['uploaded_at'] = $now; }
                elseif (in_array('created_at', $fields, true))    { $xlData['created_at']  = $now; }
                if (in_array('file_size', $fields, true)) { $xlData['file_size'] = (int)$built2['bytes']; }
                $db2->table('files')->insert($xlData);
                $fileId2 = (int)$db2->insertID();
                if ($fileId2 > 0) { $this->linkTaskFileSafe($taskId, $fileId2); }
            }
        } catch (\Throwable $excelErr) {
            log_message('warning', 'test_design_v1: Excel skipped — ' . $excelErr->getMessage());
        }

        // ── الملف الثالث: وورد الاختبار النهائي ──
        $rel3   = 'downloads/test_designs/exam/test_exam_' . $userId . '_' . $taskId . '_' . $ts . '.docx';
        $built3 = $docx->buildExamDocx($jsonObj, $rel3);
        $abs3   = rtrim(FCPATH, '/') . '/' . $built3['rel_path'];
        if (!is_file($abs3)) {
            throw new \RuntimeException('Test design Word(3) not saved: ' . $abs3);
        }
        $fileId3 = $this->insertFileRowSmart($built3['rel_path'], $userId, (int)$built3['bytes']);
        $this->linkTaskFileSafe($taskId, $fileId3);

        // ── تحديث سجل المهمة ──
        $db = $this->ensureDbAlive($db);
        $db->table('ai_jobs')->where('id', (int)$job['id'])->update([
            'result_json' => json_encode(array_merge($jsonObj, [
                'file_analysis_id' => $fileId1,
                'file_excel_id'    => $fileId2,
                'file_exam_id'     => $fileId3,
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'file_id'    => $fileId1,
            'status'     => 'done',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * ✅ معالجة أداة تقييم البحث الاستقصائي: يحفظ التقييم بحالة review للمراجعة
     * بناء الملفات يتم بعد اعتماد المستخدم عبر approve-evaluation endpoint
     */
    protected function processInquiryReportEvaluatorJob(array $job, array $jsonObj, array $payload, $db): void
    {
        // حفظ نتيجة التقييم فقط — بدون بناء ملفات
        // المستخدم سيراجع النتائج ويعتمدها قبل توليد الملفات
        $db = $this->ensureDbAlive($db);
        $db->table('ai_jobs')->where('id', (int)$job['id'])->update([
            'result_json' => json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'     => 'review',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * دمج وقائي: يملأ الحقول الأساسية من حمولة المستخدم إن كانت مفقودة من JSON الناتج.
     * لا يتدخّل إذا كانت القيمة موجودة بالفعل في $j.
     */
    protected function mergeLessonPlanFallbacks(array $j, array $payload): array
    {
        // أسماء مشتقة مباشرة من مدخلات الواجهة
        $j['teacher_name']     = trim((string) ($j['teacher_name']    ?? ($payload['teacher_name']    ?? '')));
        $j['school_name']      = trim((string) ($j['school_name']     ?? ($payload['school_name']     ?? '')));
        $j['general_subject']  = trim((string) ($j['general_subject'] ?? ($payload['subject_name']    ?? '')));
        $j['general_unit']     = trim((string) ($j['general_unit']    ?? ($payload['unit_name']       ?? '')));
        $j['general_title']    = trim((string) ($j['general_title']   ?? ($payload['lesson_title']    ?? '')));
        $j['school_class']     = trim((string) ($j['school_class']    ?? ($payload['grade_level']     ?? '')));
    
        // توحيد مصفوفات النواتج إن أعادها النموذج كنص مفرد
        foreach (['o_statement','o_assess_strategy','o_assess_tool','o_teach_strategy','o_teacher','o_student','o_duration'] as $k) {
            if (isset($j[$k]) && !is_array($j[$k])) {
                $j[$k] = [$j[$k]];
            }
        }
    
        return $j;
    }

}
