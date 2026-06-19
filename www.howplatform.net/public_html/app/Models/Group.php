<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * علاقة Many-to-Many مع الطلبة
     * pivot: group_student (group_id, student_id)
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'group_student', 'group_id', 'student_id')
                    ->withTimestamps();
    }
}
