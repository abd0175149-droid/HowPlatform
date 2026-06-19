<?php
// app/Models/UniversityModel.php
namespace App\Models;

use CodeIgniter\Model;

class UniversityModel extends Model
{
    protected $table      = 'universities';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'name',
    ];

    protected $returnType    = 'array';
    protected $useTimestamps = false;
}
