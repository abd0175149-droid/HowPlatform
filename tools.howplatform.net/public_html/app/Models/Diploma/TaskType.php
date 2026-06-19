<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class TaskType extends Model
{
    protected $connection = 'diploma';
    protected $table = 'task_types'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
