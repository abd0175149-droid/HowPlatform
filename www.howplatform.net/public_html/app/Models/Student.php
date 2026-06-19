<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        // أضف الحقول الأخرى الموجودة لديك مثل email أو university_id إن وُجدت
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_student', 'student_id', 'group_id')
                    ->withTimestamps();
    }
}
