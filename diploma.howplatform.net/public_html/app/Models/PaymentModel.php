<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table      = 'payments';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'user_id',
        'amount',
        'payment_method_id',
        'status',
        'invoice_number',
        'gateway_transaction_id',
        'payment_date',
        'notes',
        'recorded_by_admin_id',
        'pending_requests_id'
    ];

    protected $useTimestamps = false;
}
