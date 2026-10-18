<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * تعبئة رد فَطين الأولي لكل طلبات الخدمة التي لا تملك ردّاً (أُنشئت أثناء تعطّل الرد).
 *
 *   php spark chat:aifill-missing --dry                      # حصر فقط (لا إدراج)
 *   php spark chat:aifill-missing --status pending,in_progress
 *   php spark chat:aifill-missing --limit 20 --sleep 500
 *
 * غير مُزعج: يرسل إشارة لحظية (bump) فقط بلا إشعار push — مثل chat:aifill المفرد.
 */
class ChatAifillMissing extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'chat:aifill-missing';
    protected $description = 'Backfill Fateen AI first-reply for service requests that have none.';
    protected $usage       = 'chat:aifill-missing [--status pending,in_progress] [--limit N] [--dry] [--sleep ms]';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $statusOpt = (string) (CLI::getOption('status') ?? '');
        $limit     = (int) (CLI::getOption('limit') ?? 0);
        $dry       = (bool) CLI::getOption('dry');
        $sleepMs   = CLI::getOption('sleep') === null ? 400 : (int) CLI::getOption('sleep');

        $b = $db->table('service_requests sr')
            ->select('sr.id, sr.user_id, sr.subject, sr.description, sr.status, sr.created_at')
            ->where(
                "NOT EXISTS (SELECT 1 FROM service_messages sm WHERE sm.service_request_id = sr.id AND sm.sender_role = 'ai')",
                null,
                false
            )
            ->orderBy('sr.id', 'ASC');

        $statuses = array_values(array_filter(array_map('trim', explode(',', $statusOpt))));
        if (! empty($statuses)) {
            $b->whereIn('sr.status', $statuses);
        }
        if ($limit > 0) {
            $b->limit($limit);
        }

        $rows = $b->get()->getResultArray();

        // حصر حسب الحالة
        $byStatus = [];
        foreach ($rows as $r) {
            $s = (string) ($r['status'] ?? '');
            $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
        }
        CLI::write('طلبات بلا رد فَطين: ' . count($rows) . ($statuses ? ' (بعد فلترة الحالة)' : ''), 'yellow');
        foreach ($byStatus as $s => $c) {
            CLI::write("  - {$s}: {$c}");
        }

        if ($dry) {
            CLI::write('DRY RUN — لم يُدرَج شيء. IDs: ' . implode(', ', array_column($rows, 'id')), 'green');
            return;
        }
        if (empty($rows)) {
            CLI::write('لا شيء لتعبئته.', 'green');
            return;
        }

        $ok = $fail = $skip = 0;
        foreach ($rows as $r) {
            $reqId = (int) $r['id'];

            // فحص السباق: قد يكون امتلأ للتو
            $already = $db->table('service_messages')
                ->where(['service_request_id' => $reqId, 'sender_role' => 'ai'])
                ->countAllResults();
            if ($already > 0) {
                $skip++;
                continue;
            }

            $name = '';
            try {
                $u    = $db->table('users')->select('name')->where('id', (int) $r['user_id'])->get()->getRowArray();
                $name = (string) ($u['name'] ?? '');
            } catch (\Throwable $e) {
            }

            $text = \App\Libraries\AiReply::serviceFirstReply(
                (string) ($r['subject'] ?? ''),
                (string) ($r['description'] ?? ''),
                $name
            );
            if ($text === null || $text === '') {
                $fail++;
                CLI::write("  #{$reqId} فشل (null) — راجع اللوج.", 'red');
                continue;
            }

            $db->table('service_messages')->insert([
                'service_request_id' => $reqId,
                'sender_user_id'     => null,
                'sender_role'        => 'ai',
                'body'               => $text,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            $msgId = (int) $db->insertID();

            try {
                \App\Libraries\ChatSignal::bump($reqId, $msgId, 'ai', (int) $r['user_id'], null);
            } catch (\Throwable $e) {
                // غير قاتل
            }

            $ok++;
            CLI::write("  #{$reqId} تمّت التعبئة (msg {$msgId})", 'green');

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        CLI::write("انتهى. مُعبّأ={$ok}  فاشل={$fail}  متخطّى={$skip}", 'green');
    }
}
