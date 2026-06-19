<?php

namespace App\Models;

use CodeIgniter\Model;

class SupportRequestModel extends Model
{
    protected $table = 'support_requests';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'full_name',
        'email',
        'subject',
        'message',
        'status',
        'is_read',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true; 
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
