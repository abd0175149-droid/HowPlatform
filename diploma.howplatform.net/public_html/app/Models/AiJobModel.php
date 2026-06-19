<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل طوابير/طلبات الذكاء الاصطناعي
 */
class AiJobModel extends Model
{
    protected $table            = 'ai_jobs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'job_type_id', 'task_id', 'user_id',
        'payload_json', 'status', 'result_json', 'file_id',
        'error_message', 'requested_ip', 'requested_ua', 'retry_count',
        'points_deducted', 'points_cost',
        'created_at', 'updated_at',
    ];

    /**
     * إرجاع طلب واحد لنفس (user, task, jobType)، إن وُجد.
     */
    public function findForUserTaskType(int $userId, int $taskId, int $jobTypeId, ?string $studentId = null): ?array
    {
        $this->where('user_id', $userId)
             ->where('task_id', $taskId)
             ->where('job_type_id', $jobTypeId);

        if ($studentId !== null && $studentId !== '') {
            $this->groupStart()
                 ->like('payload_json', '"student_id":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_id":' . $studentId)
                 ->orLike('payload_json', '"student_number":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_number":' . $studentId)
                 ->groupEnd();
        }

        return $this->orderBy('id', 'DESC')
                    ->first() ?: null;
    }

    /**
     * التحقق من وجود استخدام مجاني سابق (job مكتمل) لنفس (user, task, jobType)
     */
    public function hasFreeUsageInTask(int $userId, int $taskId, int $jobTypeId, ?string $studentId = null): bool
    {
        $this->where('user_id', $userId)
             ->where('task_id', $taskId)
             ->where('job_type_id', $jobTypeId)
             ->where('status', 'done');

        if ($studentId !== null && $studentId !== '') {
            $this->groupStart()
                 ->like('payload_json', '"student_id":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_id":' . $studentId)
                 ->orLike('payload_json', '"student_number":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_number":' . $studentId)
                 ->groupEnd();
        }

        $job = $this->orderBy('id', 'DESC')
                    ->first();

        return $job !== null;
    }

    /**
     * البحث عن job نشط فقط (pending/processing/queued) لنفس (user, task, jobType)
     */
    public function findActiveJobForUserTaskType(int $userId, int $taskId, int $jobTypeId, ?string $studentId = null): ?array
    {
        $this->where('user_id', $userId)
             ->where('task_id', $taskId)
             ->where('job_type_id', $jobTypeId)
             ->whereIn('status', ['pending', 'processing', 'queued']);

        if ($studentId !== null && $studentId !== '') {
            $this->groupStart()
                 ->like('payload_json', '"student_id":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_id":' . $studentId)
                 ->orLike('payload_json', '"student_number":"' . $studentId . '"')
                 ->orLike('payload_json', '"student_number":' . $studentId)
                 ->groupEnd();
        }

        return $this->orderBy('id', 'DESC')
                    ->first() ?: null;
    }

    /**
     * جلب جميع jobs لنفس (user, task, jobType) مرتبة حسب id DESC (الأحدث أولاً)
     */
    public function findAllJobsForUserTaskType(int $userId, int $taskId, int $jobTypeId): array
    {
        return $this->where('user_id', $userId)
                    ->where('task_id', $taskId)
                    ->where('job_type_id', $jobTypeId)
                    ->orderBy('id', 'DESC')
                    ->findAll();
    }
}
