<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
     // الثقة ببروكسي Cloudflare Tunnel لكشف https/الـ host الصحيح
     $middleware->trustProxies(at: '*', headers:
         \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
       | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
       | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
       | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);

     $middleware->alias([
         'auth.token'   => \App\Http\Middleware\AuthenticateWithToken::class,
         'auth.sso'     => \App\Http\Middleware\EnsureSsoAuthenticated::class,
         'auth.admin'   => \App\Http\Middleware\EnsureAdmin::class,
     ]);

     // لو بدك تخليه يشتغل على كل صفحات web group مباشرة
     $middleware->web(\App\Http\Middleware\AuthenticateWithToken::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
