<?php

declare(strict_types=1);

namespace App\Models\Survey\Options;

use CodeIgniter\Model;

class SupportChannelOptionModel extends Model
{
    // خيارات "قنوات الدعم"
    protected $table      = 'survey_support_channel_options';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    public $timestamps    = false;

    protected $allowedFields = ['id','name'];
}
