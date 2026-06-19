<?php
namespace App\Models;

use CodeIgniter\Model;

class TaskSpecialtyModel extends Model
{
    protected $table            = 'task_specialties';
    protected $primaryKey       = 'task_id';   // <-- أضف هذا السطر
    protected $useAutoIncrement = false;        // لا يوجد auto-increment في هذا الجدول
    protected $allowedFields    = ['task_id', 'specialty_id'];
    protected $returnType       = 'array';
    public    $useTimestamps    = false;
}
