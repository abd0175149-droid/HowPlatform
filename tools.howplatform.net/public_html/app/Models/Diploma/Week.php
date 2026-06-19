<?php

namespace App\Models\Diploma;

use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    protected $connection = 'diploma';
    protected $table = 'weekly_contents'; // عدّل إذا الاسم مختلف
    public $timestamps = false;
    protected $guarded = [];
}
