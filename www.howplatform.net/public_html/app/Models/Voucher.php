<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'platform',
        'platform_plan_id',
        'plan_name',
        'price_amount',
        'price_currency',
        'duration_days',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'notes',
        'verify_url',
        'issued_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at'        => 'datetime',
        'price_amount'      => 'float',
        'duration_days'     => 'integer',
        'platform_plan_id'  => 'integer',
        'issued_by'         => 'integer',
    ];
}
