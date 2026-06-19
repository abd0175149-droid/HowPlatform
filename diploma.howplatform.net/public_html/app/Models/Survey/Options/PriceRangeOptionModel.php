<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class PriceRangeOptionModel extends Model
{
    // خيارات "النطاق السعري"
    protected $table      = 'survey_price_ranges';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','label'];
}
