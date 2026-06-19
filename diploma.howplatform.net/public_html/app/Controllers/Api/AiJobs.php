<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class AiJobs extends BaseController
{
    use ResponseTrait;

    /** اتصال DB مختصر */
    protected function db() { return Database::connect(); }

    /** user_id من الجلسة / Shield إن وجد */
    protected function userId(): ?int
    {
        $uid = session('user_id');
        if (!$uid && function_exists('auth') && auth()?->user()) {
            $uid = auth()->user()->id ?? null;
        }
        return $uid ? (int) $uid : null;
    }

    /** Alias للتوافق مع شيفرات سابقة */
    protected function getCurrentUserId(): ?int
    {
        return $this->userId();
    }

    /** رابط تحميل بديل عبر API (fallback) */
    protected function fileDownloadUrl(int $fileId): string
    {
        return "/api/files/{$fileId}/download";
    }

    /** إن وُجد الملف في جدول files نُرجع site_url(file_path) وإلا fallback */
    protected function resolveDownloadUrlByFileId(int $fileId): ?string
    {
        $db   = $this->db();
        $file = $db->table('files')->where('id', $fileId)->get()->getRowArray();
        if (!$file) return null;
        $path = trim((string) ($file['file_path'] ?? ''), '/');
        if ($path !== '') {
            return site_url($path);
        }
        return $this->fileDownloadUrl($fileId);
    }

    /** نوع المهمة من ai_job_types */
    protected function findTypeRow(string $typeKey): ?array
    {
        return $this->db()->table('ai_job_types')
            ->where('type_key', $typeKey)
            ->where('is_enabled', 1)
            ->get()->getRowArray() ?: null;
    }

    /** أحدث مهمة لنفس (user, type, task) */
    protected function latestJobFor(int $userId, int $jobTypeId, int $taskId): ?array
    {
        return $this->db()->table('ai_jobs')
            ->where([
                'user_id'     => $userId,
                'job_type_id' => $jobTypeId,
                'task_id'     => $taskId,
            ])
            ->orderBy('id', 'DESC')
            ->get()->getRowArray() ?: null;
    }

    /**
     * GET /api/ai-jobs/status?type_key=&task_id=
     * يعيد حالة الطلب الحالي (none|pending|processing|done|failed)
     */
    public function status()
    {
        try {
            $uid = $this->userId();
            if (!$uid) return $this->respond(['ok'=>false,'message'=>'Unauthorized'], 401);

            $typeKey = (string)($this->request->getGet('type_key') ?? '');
            $taskId  = (int)($this->request->getGet('task_id') ?? 0);
            if ($typeKey === '' || $taskId <= 0) {
                return $this->respond(['ok'=>false,'message'=>'type_key و task_id مطلوبان.'], 400);
            }

            $type = $this->findTypeRow($typeKey);
            if (!$type) return $this->respond(['ok'=>false,'message'=>'نوع المهمة غير معروف أو معطّل.'], 404);

            $job = $this->latestJobFor($uid, (int)$type['id'], $taskId);
            if (!$job) return $this->respond(['ok'=>true,'state'=>'none']);

            $status = (string)($job['status'] ?? '');
            
            // معلومات إضافية عن النقاط
            $pointsCost = (int)($type['points_cost'] ?? 30);
            $pointsDeducted = (int)($job['points_deducted'] ?? 0);
            $isFreeUsage = $pointsDeducted === 0;
            
            // توحيد: queued أو pending => pending
            if ($status === 'queued' || $status === 'pending') {
                return $this->respond([
                    'ok'    => true,
                    'state' => 'queued',
                    'job_id'=> (int)$job['id'],
                    'points_cost' => $pointsCost,
                    'is_free_usage' => $isFreeUsage,
                ]);
            }
            if ($status === 'processing') {
                return $this->respond([
                    'ok'    => true,
                    'state' => 'processing',
                    'job_id'=> (int)$job['id'],
                    'points_cost' => $pointsCost,
                    'is_free_usage' => $isFreeUsage,
                ]);
            }
            if ($status === 'done') {
                $url = null;
                if (!empty($job['file_id'])) {
                    $url = $this->resolveDownloadUrlByFileId((int)$job['file_id']);
                }

                // دعم الأدوات متعددة الملفات (مثل: تقييم البحث الاستقصائي، تصميم الاختبار)
                $downloadUrls = [];
                $resultJson = !empty($job['result_json']) ? json_decode($job['result_json'], true) : [];
                
                if (!empty($resultJson)) {
                    // أداة تقييم البحث الاستقصائي: ملفان
                    if (!empty($resultJson['file_feedback_id'])) {
                        $fbUrl = $this->resolveDownloadUrlByFileId((int)$resultJson['file_feedback_id']);
                        if ($fbUrl) {
                            $downloadUrls[] = ['label' => 'تقرير التغذية الراجعة', 'url' => $fbUrl];
                        }
                    }
                    if (!empty($resultJson['file_rubric_id'])) {
                        $rbUrl = $this->resolveDownloadUrlByFileId((int)$resultJson['file_rubric_id']);
                        if ($rbUrl) {
                            $downloadUrls[] = ['label' => 'جدول معايير التقييم', 'url' => $rbUrl];
                        }
                    }
                    
                    // أداة تصميم الاختبار: 3 ملفات
                    if (!empty($resultJson['file_analysis_id'])) {
                        $aUrl = $this->resolveDownloadUrlByFileId((int)$resultJson['file_analysis_id']);
                        if ($aUrl) {
                            $downloadUrls[] = ['label' => 'تحليل المحتوى', 'url' => $aUrl];
                        }
                    }
                    if (!empty($resultJson['file_excel_id'])) {
                        $eUrl = $this->resolveDownloadUrlByFileId((int)$resultJson['file_excel_id']);
                        if ($eUrl) {
                            $downloadUrls[] = ['label' => 'جدول المواصفات', 'url' => $eUrl];
                        }
                    }
                    if (!empty($resultJson['file_exam_id'])) {
                        $exUrl = $this->resolveDownloadUrlByFileId((int)$resultJson['file_exam_id']);
                        if ($exUrl) {
                            $downloadUrls[] = ['label' => 'الاختبار النهائي', 'url' => $exUrl];
                        }
                    }
                }

                $response = [
                    'ok'           => true,
                    'state'        => 'done',
                    'download_url' => $url,
                    'job_id'       => (int)$job['id'],
                    'points_cost' => $pointsCost,
                    'is_free_usage' => $isFreeUsage,
                ];
                
                // إضافة download_urls فقط إذا كان هناك أكثر من ملف
                if (count($downloadUrls) > 1) {
                    $response['download_urls'] = $downloadUrls;
                }

                return $this->respond($response);
            }
            if ($status === 'failed') {
                return $this->respond([
                    'ok'      => true,
                    'state'   => 'failed',
                    'job_id'  => (int)$job['id'],
                    'message' => (string)($job['error_message'] ?? 'فشل سابق — راجع السجل أو أعد الإرسال.'),
                    'points_cost' => $pointsCost,
                    'is_free_usage' => $isFreeUsage,
                ]);
            }

            return $this->respond(['ok'=>true,'state'=>'none']);
        } catch (\Throwable $e) {
            log_message('error', 'AiJobs::status failed: {msg}', ['msg'=>$e->getMessage()]);
            return $this->respond(['ok'=>false,'message'=>'Server error in status'], 500);
        }
    }

    /**
     * POST /api/ai-jobs/request
     * body: { type_key, task_id, ...payload fields }
     * ينشئ مهمة جديدة بحالة pending (الـ Runner سيحوّلها processing ثم done/failed)
     */
    public function request()
    {
        try {
            $uid = $this->userId();
            if (!$uid) return $this->respond(['ok'=>false,'message'=>'Unauthorized'], 401);

            $json    = $this->request->getJSON(true) ?? [];
            $typeKey = (string)($json['type_key'] ?? '');
            $taskId  = (int)($json['task_id']  ?? 0);

            if ($typeKey === '' || $taskId <= 0) {
                return $this->respond(['ok'=>false,'message'=>'type_key و task_id مطلوبان.'], 400);
            }

            $type = $this->findTypeRow($typeKey);
            if (!$type) return $this->respond(['ok'=>false,'message'=>'نوع المهمة غير معروف أو معطّل.'], 404);

            $db        = $this->db();
            $jobTypeId = (int)$type['id'];

            // منع التكرار لنفس (user, task, type) لو في pending/processing
            $last = $this->latestJobFor($uid, $jobTypeId, $taskId);
            if ($last) {
                $st = (string)($last['status'] ?? '');
                if ($st === 'queued' || $st === 'pending' || $st === 'processing') {
                    return $this->respond([
                        'ok'     => true,
                        'status' => 'already_active',
                        'state'  => ($st === 'queued' || $st === 'pending') ? 'queued' : $st,
                        'job_id' => (int)$last['id'],
                    ], 409);
                }
                if ($st === 'done') {
                    $url = !empty($last['file_id']) ? $this->resolveDownloadUrlByFileId((int)$last['file_id']) : null;
                    return $this->respond([
                        'ok'           => true,
                        'status'       => 'already_done',
                        'download_url' => $url,
                        'job_id'       => (int)$last['id'],
                    ]);
                }
                // st === 'failed' → نسمح بإنشاء طلب جديد
            }

            $now     = date('Y-m-d H:i:s');
            $payload = $json;
            $payload['__meta'] = [
                'ip'  => $this->request->getIPAddress(),
                'ua'  => (string)($this->request->getUserAgent() ?? ''),
                'at'  => $now,
                'src' => 'admins/ui',
            ];

            $db->table('ai_jobs')->insert([
                'user_id'      => $uid,
                'job_type_id'  => $jobTypeId,
                'task_id'      => $taskId,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'       => 'queued',     // ← متوافق مع الـ Runner
                'retry_count'  => 0,
                'requested_ip' => $this->request->getIPAddress(),
                'requested_ua' => (string)($this->request->getUserAgent() ?? ''),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $jobId = (int)$db->insertID();

            return $this->respond([
                'ok'     => true,
                'status' => 'accepted',
                'job_id' => $jobId,
                'state'  => 'queued',
                'message'=> 'تم استلام الطلب وسيُعالَج في الخلفية.',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AiJobs::request failed: {msg}', ['msg'=>$e->getMessage()]);
            return $this->respond(['ok'=>false,'message'=>'Server error in request'], 500);
        }
    }

    /** Alias: POST /api/ai-jobs/create  -> نفس request() */
    public function create()
    {
        return $this->request();
    }

    /**
     * GET /api/ai-jobs/result?type_key=...&task_id=...
     * يُعيد نتيجة الـ AI (JSON) + رابط التحميل إن وُجد عند اكتمال التنفيذ.
     */
    public function result()
    {
        try {
            $userId = $this->getCurrentUserId();
            if (!$userId) return $this->failUnauthorized('يجب تسجيل الدخول.');

            $typeKey = (string) $this->request->getGet('type_key');
            $taskId  = (int) $this->request->getGet('task_id');
            if ($typeKey === '' || $taskId <= 0) {
                return $this->failValidationErrors('type_key و task_id حقول إلزامية.');
            }

            $type = $this->findTypeRow($typeKey);
            if (!$type) return $this->failNotFound('نوع المهمة غير موجود أو غير مفعّل.');

            $job = $this->latestJobFor($userId, (int)$type['id'], $taskId);
            if (!$job) {
                return $this->respond(['ok'=>true,'state'=>'none','message'=>'لا يوجد طلب سابق.']);
            }

            if ($job['status'] !== 'done') {
                $state = ($job['status'] === 'queued' || $job['status'] === 'pending') ? 'queued' : $job['status'];
                return $this->respond(['ok'=>true,'state'=>$state,'message'=>'الطلب لم يكتمل بعد.']);
            }

            $result = json_decode((string) $job['result_json'], true);
            if (!is_array($result)) {
                return $this->respond(['ok'=>false,'state'=>'done','error'=>'Invalid result_json format.'], 500);
            }

            $downloadUrl = null;
            if (!empty($job['file_id'])) {
                $downloadUrl = $this->resolveDownloadUrlByFileId((int)$job['file_id']);
            }

            return $this->respond([
                'ok'           => true,
                'state'        => 'done',
                'result'       => $result,
                'download_url' => $downloadUrl,
                'job_id'       => (int) $job['id'],
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * GET /api/ai-jobs/show/{id}
     * عرض تفاصيل مهمة واحدة
     */
    public function show(int $id)
    {
        try {
            $userId = $this->getCurrentUserId();
            if (!$userId) return $this->failUnauthorized('يجب تسجيل الدخول.');

            $db  = $this->db();
            $job = $db->table('ai_jobs')->where('id', $id)->get()->getRowArray();

            if (!$job || (int) $job['user_id'] !== $userId) {
                return $this->failNotFound('الطلب غير موجود.');
            }

            $downloadUrl = null;
            if (!empty($job['file_id'])) {
                $downloadUrl = $this->resolveDownloadUrlByFileId((int)$job['file_id']);
            }

            return $this->respond([
                'ok'  => true,
                'job' => [
                    'id'           => (int) $job['id'],
                    'task_id'      => (int) $job['task_id'],
                    'job_type_id'  => (int) $job['job_type_id'],
                    'status'       => ($job['status'] === 'queued' || $job['status'] === 'pending') ? 'queued' : $job['status'],
                    'file_id'      => $job['file_id'] ? (int) $job['file_id'] : null,
                    'error_message'=> $job['error_message'] ?: null,
                    'created_at'   => $job['created_at'],
                    'updated_at'   => $job['updated_at'],
                ],
                'download_url' => $downloadUrl,
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/ai-jobs/rebuild
     * body: { job_id: int }
     * يُعيد بناء مهمة فاشلة (failed) بإعادة ضبطها إلى pending مع نفس payload الأصلي.
     * يرفض المهام النشطة (pending/processing) أو المهام التي لا تخص المستخدم.
     */
    public function rebuild()
    {
        try {
            $uid = $this->userId();
            if (!$uid) return $this->respond(['ok' => false, 'message' => 'Unauthorized'], 401);

            $json  = $this->request->getJSON(true) ?? [];
            $jobId = (int)($json['job_id'] ?? 0);

            if ($jobId <= 0) {
                return $this->respond(['ok' => false, 'message' => 'job_id مطلوب.'], 400);
            }

            $db  = $this->db();
            $job = $db->table('ai_jobs')->where('id', $jobId)->get()->getRowArray();

            if (!$job) {
                return $this->respond(['ok' => false, 'message' => 'المهمة غير موجودة.'], 404);
            }

            // التحقق من ملكية المهمة
            if ((int)$job['user_id'] !== $uid) {
                return $this->respond(['ok' => false, 'message' => 'غير مصرّح.'], 403);
            }

            $status = (string)($job['status'] ?? '');

            // منع إعادة البناء إذا كانت المهمة نشطة أو مكتملة
            if ($status === 'pending' || $status === 'queued' || $status === 'processing') {
                return $this->respond([
                    'ok'      => false,
                    'message' => 'المهمة قيد المعالجة حالياً، لا يمكن إعادة بنائها.',
                    'state'   => ($status === 'queued' || $status === 'pending') ? 'queued' : $status,
                ], 409);
            }

            if ($status === 'done' && !empty($job['file_id'])) {
                // المهمة مكتملة وعندها ملف — اسأل المستخدم بدلاً من إعادة البناء التلقائي
                $url = $this->resolveDownloadUrlByFileId((int)$job['file_id']);
                return $this->respond([
                    'ok'           => false,
                    'message'      => 'المهمة مكتملة بالفعل وتحتوي على ملف.',
                    'download_url' => $url,
                    'state'        => 'done',
                ], 409);
            }

            // حذف الملف التالف القديم إن وجد (للمهام المكتملة بدون ملف أو الفاشلة)
            if (!empty($job['file_id'])) {
                $oldFile = $db->table('files')->where('id', (int)$job['file_id'])->get()->getRowArray();
                if ($oldFile && !empty($oldFile['file_path'])) {
                    $absPath = rtrim(FCPATH, '/') . '/' . ltrim($oldFile['file_path'], '/');
                    if (is_file($absPath)) {
                        @unlink($absPath);
                        log_message('info', "AiJobs::rebuild — deleted stale file: {$absPath}");
                    }
                }
                // حذف سجل الملف من قاعدة البيانات
                $db->table('files')->where('id', (int)$job['file_id'])->delete();
                // حذف الربط في user_task_files
                $db->table('user_task_files')->where('file_id', (int)$job['file_id'])->delete();
            }

            // إعادة ضبط المهمة إلى pending
            $now = date('Y-m-d H:i:s');
            $db->table('ai_jobs')->where('id', $jobId)->update([
                'status'        => 'queued',
                'retry_count'   => 0,
                'error_message' => null,
                'result_json'   => null,
                'file_id'       => null,
                'points_deducted' => 0,
                'updated_at'    => $now,
            ]);

            log_message('info', "AiJobs::rebuild — job #{$jobId} reset to pending by user #{$uid}");

            return $this->respond([
                'ok'      => true,
                'state'   => 'queued',
                'job_id'  => $jobId,
                'message' => 'تم إعادة إرسال المهمة للمعالجة.',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AiJobs::rebuild failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->respond(['ok' => false, 'message' => 'Server error in rebuild'], 500);
        }
    }

    /**
     * POST /api/ai-jobs/rebuild-file
     * body: { job_id: int }
     * يعيد بناء ملف DOCX لمهمة مكتملة مسبقاً (done) لتجاوز مشاكل التلف دون الحاجة لاستدعاء الذكاء الاصطناعي.
     */
    public function rebuildFile()
    {
        try {
            $uid = $this->userId();
            if (!$uid) return $this->respond(['ok' => false, 'message' => 'Unauthorized'], 401);

            $json  = $this->request->getJSON(true) ?? [];
            $jobId = (int)($json['job_id'] ?? 0);

            if ($jobId <= 0) {
                return $this->respond(['ok' => false, 'message' => 'job_id مطلوب.'], 400);
            }

            $db  = $this->db();
            $job = $db->table('ai_jobs')->where('id', $jobId)->get()->getRowArray();

            if (!$job) {
                return $this->respond(['ok' => false, 'message' => 'المهمة غير موجودة.'], 404);
            }

            if ((int)$job['user_id'] !== $uid && !$this->isAdmin()) {
                return $this->respond(['ok' => false, 'message' => 'غير مصرّح.'], 403);
            }

            if ($job['status'] !== 'done' || empty($job['result_json'])) {
                return $this->respond([
                    'ok'      => false,
                    'message' => 'لا يمكن إعادة بناء الملف إلا للمهام المكتملة (done) والتي تمتلك نتيجة (result).',
                    'state'   => $job['status']
                ], 409);
            }

            // استدعاء AiJobsRunner لتشغيل بناء الملف فقط
            $runner = new \App\Controllers\Cron\AiJobsRunner();
            $runner->rebuildFileForJob($jobId);

            return $this->respond([
                'ok'      => true,
                'message' => 'تمت إعادة بناء الملف بنجاح.'
            ]);

        } catch (\Throwable $e) {
            log_message('error', "AiJobs::rebuildFile — Error: " . $e->getMessage());
            return $this->failServerError('خطأ أثناء بناء الملف: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/ai-jobs/diag  — تشخيص مرن
     */
    public function diag()
    {
        try {
            $db = $this->db();
            $out = [
                'ok'      => true,
                'user_id' => $this->userId(),
                'tables'  => [],
            ];
            foreach (['ai_job_types','ai_jobs','files'] as $t) {
                $exists = $db->tableExists($t);
                $info   = ['exists'=>$exists,'columns'=>[]];
                if ($exists) {
                    $info['columns'] = array_map(fn($f)=>$f->name, $db->getFieldData($t));
                }
                $out['tables'][$t] = $info;
            }
            $out['lesson_plan_type'] = $db->table('ai_job_types')->where('type_key','lesson_plan_v1')->get()->getRowArray() ?: null;
            return $this->respond($out);
        } catch (\Throwable $e) {
            return $this->respond(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }

    /**
     * التحقق مما إذا كان المستخدم مديراً
     */
    protected function isAdmin(): bool
    {
        $userId = session('user_id');
        $roleId = session('role');
        if (!$userId || !$roleId) return false;
        return in_array((int) $roleId, [1, 2, 4], true);
    }
}
