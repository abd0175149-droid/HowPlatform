<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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

        // aliases للميدلويرات
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\AuthenticateWithToken::class,
        ]);

        // ضم ميدلوير التوكن إلى مجموعة web لضبط auth()->user()
        $middleware->web(\App\Http\Middleware\AuthenticateWithToken::class);
    })
    // ← سجّل فقط المزودات الموجودة فعلاً
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class, // مهم لتسجيل مزوّد "generic"
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
