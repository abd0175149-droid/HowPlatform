<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class FeatureOptionModel extends Model
{
    // خيارات "ميزات المنصة"
    protected $table      = 'survey_feature_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','name'];
}
