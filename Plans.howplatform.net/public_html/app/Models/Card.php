<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $table = 'cards';

    protected $fillable = [
        'code', 'type', 'plan_id', 'points', 'status', 'batch',
        'expires_at', 'redeemed_by', 'redeemed_at', 'created_by',
        'issued_by', 'buyer_name', 'buyer_email', 'buyer_phone',
        'price_amount', 'price_currency', 'duration_days',
    ];

    protected $casts = [
        'plan_id'       => 'int',
        'points'        => 'int',
        'redeemed_by'   => 'int',
        'created_by'    => 'int',
        'issued_by'     => 'int',
        'price_amount'  => 'decimal:2',
        'duration_days' => 'int',
        'expires_at'    => 'datetime',
        'redeemed_at'   => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
