<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class ChallengeOptionModel extends Model
{
    protected $table      = 'survey_challenge_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['id','name'];
    public $timestamps = false;
}
