<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Controllers\Cron\AiJobsRunner;

class AiJobsRun extends BaseCommand
{
    protected $group       = 'ai';
    protected $name        = 'ai:run';
    protected $description = 'Run pending AI jobs (CLI wrapper for AiJobsRunner::run)';
    protected $usage       = 'ai:run [--key=SECRET] [--debug]';

    public function run(array $params)
    {
        $key   = CLI::getOption('key') ?? (getenv('CRON_SECRET') ?: '');
        $debug = CLI::getOption('debug') ? 1 : 0;

        $runner   = new AiJobsRunner();
        $response = $runner->run($key, $debug);

        // اطبع جسم الاستجابة
        if (is_object($response) && method_exists($response, 'getBody')) {
            CLI::write($response->getBody());
        } else {
            // احتياط
            $out = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE);
            CLI::write($out);
        }
    }
}
