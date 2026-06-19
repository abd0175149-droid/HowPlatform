<?php
namespace App\Models;

use CodeIgniter\Model;

class FateenUsageLogModel extends Model
{
    protected $table      = 'fateen_usage_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public    $useTimestamps = false;

    protected $allowedFields = [
        'user_id',
        'used_at',
        'success'
    ];
}
