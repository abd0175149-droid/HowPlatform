<?php

namespace App\Models;

use CodeIgniter\Model;

class UserActiveSessionModel extends Model
{
    protected $table            = 'user_active_sessions';
    protected $primaryKey       = 'user_id';
    protected $useAutoIncrement = false; 
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['user_id','session_id','user_agent','ip_address','updated_at','created_at'];
}
