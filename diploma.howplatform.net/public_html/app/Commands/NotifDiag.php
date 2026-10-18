<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** فحص الإشعارات + أنواع الحسابات. php spark notif:diag (تشخيصي مؤقت) */
class NotifDiag extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'notif:diag';
    protected $description = 'Inspect notifications + account types + admin users.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        if (($params[0] ?? '') === 'clean') {
            $db->table('notifications')->like('title', 'اختبار', 'after')->delete();
            CLI::write('deleted test notifications. total = ' . $db->table('notifications')->countAllResults(), 'green');
            return;
        }

        CLI::write('-- account_types --', 'yellow');
        foreach ($db->table('account_types')->get()->getResultArray() as $r) {
            CLI::write('  id=' . $r['id'] . ' name=' . ($r['name'] ?? '?'));
        }

        CLI::write('-- users count per account_type --', 'yellow');
        foreach ($db->query('SELECT account_type_id, COUNT(*) c FROM users GROUP BY account_type_id')->getResultArray() as $r) {
            CLI::write('  type=' . $r['account_type_id'] . ' users=' . $r['c']);
        }

        CLI::write('-- recent notifications (8) --', 'yellow');
        foreach ($db->table('notifications')->orderBy('id', 'DESC')->limit(8)->get()->getResultArray() as $r) {
            CLI::write('  #' . $r['id'] . ' u=' . $r['user_id'] . ' | ' . $r['title'] . ' | ' . $r['created_at']);
        }
        CLI::write('  total = ' . $db->table('notifications')->countAllResults());

        if (($params[0] ?? '') === 'test') {
            CLI::write('-- self test --', 'yellow');
            $a = \App\Libraries\NotificationService::pushToAdmins('اختبار آدمن', 'رسالة اختبار للآدمن', '/admins#service_chat');
            CLI::write('  pushToAdmins inserted = ' . $a, $a > 0 ? 'green' : 'red');
            $task = $db->table('tasks')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
            if ($task) {
                $t = \App\Libraries\NotificationService::pushToTaskStudents((int) $task['id'], 'اختبار مهمة', 'إشعار اختبار لمهمة', '#/tasks', false);
                CLI::write('  pushToTaskStudents(task ' . $task['id'] . ') inserted = ' . $t, $t > 0 ? 'green' : 'yellow');
            }
            CLI::write('  total after test = ' . $db->table('notifications')->countAllResults());
        }
    }
}
