<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class ContentTypeOptionModel extends Model
{
    // خيارات "تفضيلات المحتوى"
    protected $table      = 'survey_content_type_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','name'];
}
