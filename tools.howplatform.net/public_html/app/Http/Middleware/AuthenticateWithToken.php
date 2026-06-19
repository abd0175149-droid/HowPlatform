<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $_COOKIE['howplatform_token'] ?? null;

        if ($token) {
            $response = Http::withToken($token)
                ->get(env('AUTH_API_URL', 'https://auth.howplatform.net/api/user'));

            if ($response->ok()) {
                $userData = $response->json();

                // نخزن بيانات المستخدم في السيشن
                $request->session()->put('auth_user', $userData);
            }
        }

        return $next($request);
    }
}
