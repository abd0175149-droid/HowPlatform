<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSsoAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('auth_user')) {
            return redirect()->route('login', ['redirect' => $request->fullUrl()])
                ->with('status', 'الرجاء تسجيل الدخول أولاً.');
        }

        return $next($request);
    }
}
