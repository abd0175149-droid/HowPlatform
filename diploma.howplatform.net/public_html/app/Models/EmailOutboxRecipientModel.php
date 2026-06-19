<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EmailOutboxRecipientModel extends Model
{
    protected $table         = 'email_outbox_recipients';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'outbox_id','user_id','email','status','reason','sent_at',
    ];
}
