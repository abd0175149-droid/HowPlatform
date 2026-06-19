<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class TimingOptionModel extends Model
{
    // خيارات "توقيت الدعم المفضّل"
    protected $table      = 'survey_timing_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','name'];
}
