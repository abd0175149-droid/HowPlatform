<?php

namespace App\Models;

use CodeIgniter\Model;

class DiplomaTypeModel extends Model
{
    protected $table = 'diploma_types';
    protected $allowedFields = ['name'];
    protected $returnType    = 'array';

}
