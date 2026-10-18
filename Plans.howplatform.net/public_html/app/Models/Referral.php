<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $table = 'referrals';

    protected $fillable = ['referrer_id', 'referred_id', 'status', 'rewarded_at'];

    protected $casts = [
        'referrer_id' => 'int',
        'referred_id' => 'int',
        'rewarded_at' => 'datetime',
    ];
}
