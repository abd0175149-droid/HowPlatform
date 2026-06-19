<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table      = 'courses';
    protected $primaryKey = 'id';

    protected $allowedFields = ['name', 'code', 'description'];
    protected $returnType    = 'array';
    public    $useTimestamps = false;
}
