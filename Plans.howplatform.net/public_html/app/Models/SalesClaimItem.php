<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesClaimItem extends Model
{
    protected $table = 'sales_claim_items';

    protected $fillable = [
        'claim_id', 'card_id', 'amount', 'paid_amount', 'is_paid', 'paid_at',
    ];

    protected $casts = [
        'claim_id'    => 'int',
        'card_id'     => 'int',
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_paid'     => 'bool',
        'paid_at'     => 'datetime',
    ];

    public function claim()
    {
        return $this->belongsTo(SalesClaim::class, 'claim_id');
    }

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id');
    }
}
