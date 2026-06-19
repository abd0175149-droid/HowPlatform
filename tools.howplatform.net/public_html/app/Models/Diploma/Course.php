<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $connection = 'diploma';
    protected $table = 'courses'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
