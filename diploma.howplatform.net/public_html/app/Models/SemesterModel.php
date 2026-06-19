<?php

namespace App\Models;

use CodeIgniter\Model;

class SemesterModel extends Model
{
    protected $table      = 'semesters';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'academic_year_id',
        'name',
    ];

    protected $returnType    = 'array';
    protected $useTimestamps = false;
}
