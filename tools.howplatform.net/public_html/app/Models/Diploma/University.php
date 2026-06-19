<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    protected $connection = 'diploma';
    protected $table = 'universities'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
