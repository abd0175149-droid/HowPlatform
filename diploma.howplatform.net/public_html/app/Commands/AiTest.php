<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** تشخيص نداء Gemini لرد فَطين. php spark ai:test (تشخيصي مؤقت) */
class AiTest extends BaseCommand
{
    protected $group       = 'Diagnostics';
    protected $name        = 'ai:test';
    protected $description = 'Diagnose the Gemini call used for the service AI first-reply.';

    public function run(array $params)
    {
        $model  = getenv('AI_GEMINI_MODEL') ?: 'gemini-2.0-flash';
        $apiKey = (string) (getenv('AI_GEMINI_API_KEY') ?: '');
        CLI::write('AI_GEMINI_MODEL = ' . $model, 'yellow');
        CLI::write('API key set = ' . ($apiKey !== '' ? 'yes (len ' . strlen($apiKey) . ')' : 'NO'), 'yellow');

        // 1) عبر المكتبة
        $r = \App\Libraries\AiReply::serviceFirstReply(
            'مساعدة في مقال تحليلي نقدي',
            'أريد مساعدة في كتابة مقال تحليلي نقدي حول التفكير الناقد وآلياته في التدريس.',
            'عبدالله'
        );
        CLI::write('AiReply::serviceFirstReply => ' . ($r === null ? 'NULL' : mb_substr($r, 0, 200)), $r === null ? 'red' : 'green');

        // 2) نداء خام لمعرفة الحالة/الجسم
        $url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
        $client = \Config\Services::curlrequest(['timeout' => 25]);
        $res    = $client->post($url, [
            'json'        => ['contents' => [['role' => 'user', 'parts' => [['text' => 'قل: مرحبا']]]]],
            'http_errors' => false,
        ]);
        CLI::write('RAW status = ' . $res->getStatusCode());
        CLI::write('RAW body  = ' . substr((string) $res->getBody(), 0, 500));
    }
}
