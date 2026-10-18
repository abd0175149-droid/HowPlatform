<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** فحص رسائل الشات الأخيرة + رسائل فَطين. php spark chat:diag (تشخيصي مؤقت) */
class ChatDiag extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'chat:diag';
    protected $description = 'Inspect recent service_messages + AI replies.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $ai = $db->table('service_messages')->where('sender_role', 'ai')->countAllResults();
        CLI::write('AI messages total = ' . $ai, 'yellow');

        $rows = $db->table('service_messages')
            ->select('id, service_request_id, sender_user_id, sender_role, LEFT(body,60) AS snippet, created_at')
            ->orderBy('id', 'DESC')->limit(8)->get()->getResultArray();

        foreach ($rows as $r) {
            CLI::write(sprintf(
                '#%s req=%s sender=%s role=%s | %s | %s',
                $r['id'], $r['service_request_id'],
                $r['sender_user_id'] ?? 'NULL', $r['sender_role'] ?? '-',
                str_replace("\n", ' ', (string) $r['snippet']), $r['created_at']
            ));
        }

        CLI::write('--- recent service_requests ---', 'yellow');
        $reqs = $db->table('service_requests')
            ->select('id, user_id, status, LEFT(subject,40) AS subject, created_at')
            ->orderBy('id', 'DESC')->limit(6)->get()->getResultArray();
        foreach ($reqs as $r) {
            CLI::write(sprintf('req#%s user=%s status=%s | %s | %s',
                $r['id'], $r['user_id'], $r['status'], $r['subject'], $r['created_at']));
        }
    }
}
