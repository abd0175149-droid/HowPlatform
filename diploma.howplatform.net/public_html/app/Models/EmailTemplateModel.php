<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EmailTemplateModel extends Model
{
    protected $table         = 'email_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'admin_id','name','subject','preheader','body_template','is_shared',
        'created_at','updated_at',
    ];
}
