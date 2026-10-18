<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $table = 'idempotency_records';

    protected $fillable = [
        'service_client', 'idempotency_key', 'endpoint', 'response_code', 'response_body',
    ];

    protected $casts = [
        'response_code' => 'int',
    ];
}
