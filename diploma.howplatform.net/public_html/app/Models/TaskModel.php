<?php
namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل جدول المهام
 * جدول tasks:
 * - id bigint PK
 * - weekly_content_id bigint
 * - task_type_id int
 * - title varchar(255)
 * - description text
 * - is_for_all_specialties tinyint
 * - visible_from_date datetime
 * - due_date datetime
 */
class TaskModel extends Model
{
    protected $table         = 'tasks';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'weekly_content_id', 'task_type_id', 'title', 'description', 'task_prompt',
        'ai_tool_version', 'is_admin_only', 'is_for_all_specialties', 'visible_from_date', 'due_date'
    ];
    protected $returnType    = 'array';
    public    $useTimestamps = false;
}
