<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $table = 'point_rules';

    protected $fillable = ['event_key', 'kind', 'points', 'is_active', 'description'];

    protected $casts = [
        'points'    => 'int',
        'is_active' => 'bool',
    ];
}
