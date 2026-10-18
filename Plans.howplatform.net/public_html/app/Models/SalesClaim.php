<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesClaim extends Model
{
    protected $table = 'sales_claims';

    protected $fillable = [
        'claim_code', 'user_id', 'date_from', 'date_to',
        'invoices_count', 'invoices_total', 'status', 'created_by',
    ];

    protected $casts = [
        'user_id'        => 'int',
        'invoices_count' => 'int',
        'invoices_total' => 'decimal:2',
        'created_by'     => 'int',
        'date_from'      => 'date',
        'date_to'        => 'date',
    ];

    public function items()
    {
        return $this->hasMany(SalesClaimItem::class, 'claim_id');
    }
}
