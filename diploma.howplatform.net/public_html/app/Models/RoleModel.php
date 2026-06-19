<?php
namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table      = 'account_types';   // ← جدول الأدوار
    protected $primaryKey = 'id';

    protected $allowedFields = ['name'];
    public    $returnType    = 'array';
}
