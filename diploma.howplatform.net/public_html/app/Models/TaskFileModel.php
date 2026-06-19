<?php
namespace App\Models;

use CodeIgniter\Model;

/**
 * موديل جدول ربط المهام بالملفات
 * جدول task_files:
 * - id int PK
 * - task_id bigint
 * - file_id bigint
 * - display_order int
 */
class TaskFileModel extends Model
{
    protected $table         = 'task_files';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['task_id', 'file_id', 'display_order'];
    protected $returnType    = 'array';
    public    $useTimestamps = false;
}
