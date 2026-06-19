<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $connection = 'diploma';
    protected $table = 'academic_years'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
