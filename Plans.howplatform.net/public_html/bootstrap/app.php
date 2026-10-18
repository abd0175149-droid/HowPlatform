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
        // خلف Cloudflare Tunnel: ثق بالبروكسي لكشف https/host الصحيح
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
          | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);

        // مصادقة الخدمات الداخلية (المنصّات الأخرى تستدعي Plans API)
        $middleware->alias([
            'auth.apikey' => \App\Http\Middleware\AuthenticateServiceClient::class,
            'auth.sso'    => \App\Http\Middleware\EnsureSsoAuthenticated::class,
            'auth.admin'  => \App\Http\Middleware\EnsureAdmin::class,
            'auth.sales'  => \App\Http\Middleware\EnsureSalesAccess::class,
        ]);

        // مصادقة SSO عبر الكوكي المشترك على صفحات web فقط (الـ API يبقى عديم الحالة)
        $middleware->web(\App\Http\Middleware\AuthenticateWithToken::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ردّ JSON موحّد لأخطاء مسار الـ API
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
