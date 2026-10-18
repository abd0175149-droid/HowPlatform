<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'start_at', 'end_at',
        'source', 'card_id', 'created_by', 'note',
    ];

    protected $casts = [
        'user_id'    => 'int',
        'plan_id'    => 'int',
        'card_id'    => 'int',
        'created_by' => 'int',
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
