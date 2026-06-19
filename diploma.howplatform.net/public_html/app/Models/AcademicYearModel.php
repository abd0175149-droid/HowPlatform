<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table      = 'academic_years';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'label',
        'start_date',
        'end_date',
    ];

    protected $returnType    = 'array';
    protected $useTimestamps = false;
}
