<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTaskProgress extends Model
{
    protected $table = 'student_task_progress';

    protected $fillable = [
        'student_id',
        'task_id',
        'submission_status',
        'grade',
        'notes',
        'updated_by'
    ];
    protected $casts = [
        'grade' => 'string',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
