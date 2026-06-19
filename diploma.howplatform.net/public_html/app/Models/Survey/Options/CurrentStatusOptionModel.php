<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class CurrentStatusOptionModel extends Model
{
    // خيارات "الوضع الحالي"
    protected $table      = 'survey_current_status_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','name'];
}
