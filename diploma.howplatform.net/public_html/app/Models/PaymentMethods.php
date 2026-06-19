<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentMethodModel extends Model
{
    protected $table = 'payment_methods';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name'];
    protected $useTimestamps = false;
}
