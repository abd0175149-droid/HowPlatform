<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EmailOutboxModel extends Model
{
    protected $table         = 'email_outbox';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'admin_id','subject','preheader','body_template','dry_run','is_test',
        'total_recipients','sent_count','failed_count','created_at','finished_at',
    ];
}
