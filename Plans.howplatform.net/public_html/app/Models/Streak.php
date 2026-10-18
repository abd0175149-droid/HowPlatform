<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Streak extends Model
{
    protected $table = 'streaks';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'current_streak', 'last_login_date'];

    protected $casts = [
        'user_id'         => 'int',
        'current_streak'  => 'int',
        'last_login_date' => 'date',
    ];
}
