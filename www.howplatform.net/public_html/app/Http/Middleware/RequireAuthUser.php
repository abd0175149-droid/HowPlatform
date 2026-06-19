<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAuthUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('auth_user')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $redirect = urlencode($request->fullUrl());
            return redirect()->away("https://auth.howplatform.net/login?redirect={$redirect}");
        }
        return $next($request);
    }
}
