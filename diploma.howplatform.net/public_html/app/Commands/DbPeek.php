<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** فحص مخطط سريع: php spark db:peek service_messages  (أمر تشخيصي مؤقت) */
class DbPeek extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'db:peek';
    protected $description = 'Print SHOW CREATE TABLE for a table.';
    protected $usage       = 'db:peek <table>';

    public function run(array $params)
    {
        $table = $params[0] ?? 'service_messages';
        $db    = \Config\Database::connect();
        try {
            $row = $db->query('SHOW CREATE TABLE ' . $db->escapeIdentifiers($table))->getRowArray();
            CLI::write((string) (array_values($row)[1] ?? '(none)'));
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
        }
    }
}
