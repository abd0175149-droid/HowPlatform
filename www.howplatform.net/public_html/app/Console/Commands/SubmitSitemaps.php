<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole as GSC;
use Illuminate\Support\Facades\Storage;

class SubmitSitemaps extends Command
{
    protected $signature = 'seo:submit-sitemaps';
    protected $description = 'Submit site maps to Google Search Console';

    public function handle(): int
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->addScope('https://www.googleapis.com/auth/webmasters');

        // تحميل التوكن المحفوظ
        $json = Storage::disk('local')->get('gsc_token.json');
        $token = json_decode($json, true) ?: [];
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired() && isset($token['refresh_token'])) {
            $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            Storage::disk('local')->put('gsc_token.json', json_encode($client->getAccessToken()));
        }

        $service = new GSC($client);

        $sites = [
            ['siteUrl' => 'https://www.howplatform.net',     'sitemap' => 'https://www.howplatform.net/sitemap.xml'],
            ['siteUrl' => 'https://diploma.howplatform.net', 'sitemap' => 'https://diploma.howplatform.net/sitemap.xml'],
        ];

        foreach ($sites as $s) {
            try {
                $service->sitemaps->submit($s['siteUrl'], $s['sitemap']);
                $this->info("Submitted: {$s['sitemap']} to {$s['siteUrl']}");
            } catch (\Throwable $e) {
                $this->error("Failed for {$s['siteUrl']}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
