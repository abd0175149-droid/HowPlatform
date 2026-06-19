<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $connection = 'diploma';
    protected $table = 'semesters'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
