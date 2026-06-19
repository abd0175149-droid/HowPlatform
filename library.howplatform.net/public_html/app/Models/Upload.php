<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    // ⚠️ غيّرها إن كان اسم الجدول غير "uploads"
    protected $table = 'uploads';

    // نسمح لكل الحقول (بدّلها إلى $fillable إذا بدّك تقييد أدق)
    protected $guarded = [];

    protected $casts = [
        'id'           => 'integer',
        'size'         => 'integer',
        'grade_id'     => 'integer',
        'subject_id'   => 'integer',
        'semester_id'  => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
