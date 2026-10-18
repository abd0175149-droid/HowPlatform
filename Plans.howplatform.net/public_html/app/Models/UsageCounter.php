<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    protected $table = 'usage_counters';

    protected $fillable = ['user_id', 'feature_key', 'period_key', 'used'];

    protected $casts = [
        'user_id' => 'int',
        'used'    => 'int',
    ];
}
