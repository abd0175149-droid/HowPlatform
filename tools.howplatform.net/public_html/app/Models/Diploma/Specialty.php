<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $connection = 'diploma';
    protected $table = 'specialties'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
