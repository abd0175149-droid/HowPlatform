<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';

    // دفتر غير قابل للتعديل: قيد واحد فقط (created_at من DEFAULT)، لا updated_at.
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'points', 'balance_after', 'kind', 'idempotency_key',
        'event_type', 'event_ref_type', 'event_ref_id', 'service_client', 'description',
    ];

    protected $casts = [
        'user_id'       => 'int',
        'points'        => 'int',
        'balance_after' => 'int',
    ];
}
