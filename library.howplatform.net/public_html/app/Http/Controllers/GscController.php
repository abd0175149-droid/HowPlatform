<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole as GSC;

class GscController extends Controller
{
    /** تهيئة عميل Google */
    private function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        // ⚠️ ضع CLIENT_ID و CLIENT_SECRET في .env
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(url('/gsc/callback'));
        $client->setAccessType('offline'); // للحصول على refresh token
        $client->setPrompt('consent');     // أول مرة فقط
        $client->addScope('https://www.googleapis.com/auth/webmasters'); // صلاحية Search Console
        return $client;
    }

    /** خطوة 1: توجيه المستخدم لتسجيل الدخول */
    public function connect()
    {
        $client = $this->makeClient();
        return redirect()->away($client->createAuthUrl());
    }

    /** خطوة 2: استلام الكود وتخزين التوكنات */
    public function callback(Request $request)
    {
        $client = $this->makeClient();
        $code   = $request->query('code');
        if (!$code) {
            return response('Missing code', 400);
        }

        // تبادل الكود مقابل Access + Refresh Token
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            return response('OAuth error: ' . $token['error'], 400);
        }

        // حفظ التوكنات بشكل آمن (هنا اخترنا التخزين ملفيًا)
        Storage::disk('local')->put('gsc_token.json', json_encode($token));
        return response('Google Search Console connected. You can now POST /gsc/submit', 200);
    }

    /** خطوة 3: إرسال الخرائط برمجيًا */
    public function submit(Request $request)
    {
        $client = $this->makeClient();

        // تحميل التوكن المحفوظ
        $json = Storage::disk('local')->get('gsc_token.json');
        $token = json_decode($json, true) ?: [];
        $client->setAccessToken($token);

        // إن انتهت صلاحية التوكن، حدّثه باستخدام refresh_token
        if ($client->isAccessTokenExpired() && isset($token['refresh_token'])) {
            $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            Storage::disk('local')->put('gsc_token.json', json_encode($client->getAccessToken()));
        }

        $service = new GSC($client);

        // ⚠️ عدّل القيم حسب مواقعك
        $sites = [
            ['siteUrl' => 'https://www.howplatform.net',     'sitemap' => 'https://www.howplatform.net/sitemap.xml'],
            ['siteUrl' => 'https://diploma.howplatform.net', 'sitemap' => 'https://diploma.howplatform.net/sitemap.xml'],
        ];

        $ok = [];
        $fail = [];

        foreach ($sites as $s) {
            try {
                // تعليمة الإرسال الرسمية لسایت ماب
                $service->sitemaps->submit($s['siteUrl'], $s['sitemap']); // :contentReference[oaicite:3]{index=3}
                $ok[] = $s;
            } catch (\Throwable $e) {
                $fail[] = ['s' => $s, 'e' => $e->getMessage()];
            }
        }

        return response()->json(['ok' => $ok, 'fail' => $fail]);
    }
}
