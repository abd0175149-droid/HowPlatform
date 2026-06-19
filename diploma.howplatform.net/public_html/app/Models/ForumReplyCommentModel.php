<?php
declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل ردود التعليقات في المنتدى
 */
class ForumReplyCommentModel extends Model
{
    protected $table            = 'forum_reply_comments';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'user_id', 'task_id', 'ai_job_id', 'comment_text', 'reply_type',
        'reply_instructions', 'reply_result', 'sort_order', 'status',
        'created_at', 'updated_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * جلب جميع ردود التعليقات لمستخدم ومهمة معينة
     */
    public function getCommentsForUserTask(int $userId, int $taskId): array
    {
        return $this->where('user_id', $userId)
                    ->where('task_id', $taskId)
                    ->orderBy('sort_order', 'ASC')
                    ->orderBy('created_at', 'ASC')
                    ->findAll();
    }

    /**
     * التحقق من عدد الردود (حد أقصى 2)
     */
    public function canCreateNewComment(int $userId, int $taskId): bool
    {
        $count = $this->where('user_id', $userId)
                     ->where('task_id', $taskId)
                     ->countAllResults();
        return $count < 2;
    }

    /**
     * جلب الرد التالي (sort_order)
     */
    public function getNextSortOrder(int $userId, int $taskId): int
    {
        $max = $this->where('user_id', $userId)
                   ->where('task_id', $taskId)
                   ->selectMax('sort_order')
                   ->first();
        return (int)($max['sort_order'] ?? 0) + 1;
    }
}

