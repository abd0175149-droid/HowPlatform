<?php
namespace App\Models;

use CodeIgniter\Model;

class UserProfileModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';   
    protected $updatedField  = 'updated_at';    

    protected $allowedFields = [
        'name','email','password','verification_token','is_verified',
        'account_type_id','is_complete','phone_number',
        'university_id','specialty_id','diploma_type_id','academic_year_id',
        'gender','birth_date','updated_at'
    ];
}
