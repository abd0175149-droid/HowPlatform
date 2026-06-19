<?php
namespace App\Models;

use CodeIgniter\Model;

class TaskTypeModel extends Model
{
    protected $table         = 'task_types';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name'];
    protected $returnType    = 'array';
    public    $useTimestamps = false;
}
