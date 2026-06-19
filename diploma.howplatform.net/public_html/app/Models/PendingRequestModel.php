<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PendingRequestModel extends Model
{
    protected $table      = 'pending_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'user_id',
        'plan_type_id',
        'request_date',
        'status', // 'pending' | 'approved' | 'rejected'
    ];
}
