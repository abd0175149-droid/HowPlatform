<?php

namespace App\Models;

use CodeIgniter\Model;

class SpecialtyModel extends Model
{
    protected $table = 'specialties';
    protected $allowedFields = ['name'];
}
