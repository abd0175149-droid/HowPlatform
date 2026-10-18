<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** توليد رد فَطين لطلب لا يملك رداً بعد. php spark chat:aifill <requestId> */
class ChatAifill extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'chat:aifill';
    protected $description = 'Generate Fateen AI first-reply for an existing service request.';
    protected $usage       = 'chat:aifill <requestId>';

    public function run(array $params)
    {
        $reqId = (int) ($params[0] ?? 0);
        if ($reqId <= 0) {
            CLI::error('Usage: chat:aifill <requestId>');
            return;
        }

        $db  = \Config\Database::connect();
        $req = $db->table('service_requests')->where('id', $reqId)->get()->getRowArray();
        if (! $req) {
            CLI::error('Request not found: ' . $reqId);
            return;
        }

        $already = $db->table('service_messages')
            ->where(['service_request_id' => $reqId, 'sender_role' => 'ai'])->countAllResults();
        if ($already > 0) {
            CLI::write('Already has an AI message — skipping.', 'yellow');
            return;
        }

        $name = '';
        try {
            $u = $db->table('users')->select('name')->where('id', $req['user_id'])->get()->getRowArray();
            $name = (string) ($u['name'] ?? '');
        } catch (\Throwable $e) {
        }

        $text = \App\Libraries\AiReply::serviceFirstReply(
            (string) $req['subject'],
            (string) $req['description'],
            $name
        );
        if ($text === null) {
            CLI::error('AI returned null.');
            return;
        }

        $db->table('service_messages')->insert([
            'service_request_id' => $reqId,
            'sender_user_id'     => null,
            'sender_role'        => 'ai',
            'body'               => $text,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);
        $msgId = (int) $db->insertID();
        \App\Libraries\ChatSignal::bump($reqId, $msgId, 'ai', (int) $req['user_id'], null);

        CLI::write('Inserted AI message #' . $msgId . ' for request ' . $reqId, 'green');
        CLI::write(mb_substr($text, 0, 160) . '…');
    }
}
