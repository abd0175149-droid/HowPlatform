<?php
namespace App\Models;

use CodeIgniter\Model;

class UniversitySpecialtyModel extends Model
{
    protected $table         = 'university_specialties';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['university_id', 'specialty_id'];
}
