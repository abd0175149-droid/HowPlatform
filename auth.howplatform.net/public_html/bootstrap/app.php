<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

use App\Http\Middleware\SsoSessionRefresh;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;

use App\Http\Middleware\VerifyApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
          api: [
        __DIR__.'/../routes/api.php',
        __DIR__.'/../routes/mobile.php', 
    ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // الثقة ببروكسي Cloudflare Tunnel (الأصل HTTP، الحافة HTTPS) لكشف https/الـ host الصحيح
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(replace: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,

            SsoSessionRefresh::class,

            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            
        ]);

        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,

        ]);

        $middleware->alias([
             'verify.apikey' => \App\Http\Middleware\VerifyApiKey::class,

        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // معالجة ألطف لخطأ 419 لو انتهت السشن أثناء POST
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'انتهت جلستك. حدّث الصفحة ثم أعد المحاولة.',
                    'code'    => 419,
                ], 419);
            }
            $login = url('/login') . '?redirect=' . urlencode($request->fullUrl());
            return redirect()->away($login)->with('error', 'انتهت جلستك، الرجاء تسجيل الدخول مجددًا.');
        });
    })
    ->create();
