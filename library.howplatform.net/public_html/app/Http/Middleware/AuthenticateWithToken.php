<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Auth\GenericUser;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next)
    {
        $token   = $_COOKIE['howplatform_token'] ?? null;
        $session = $request->session();

        // لا يوجد توكن: امسح أي مستخدم قديم وأكمل كزائر
        if (!$token) {
            $session->forget(['auth_user', 'auth_user_fetched_at', 'auth_token_hash']);
            return $next($request);
        }

        $tokenHash = hash('sha256', $token);
        $lastHash  = $session->get('auth_token_hash');
        $fetchedAt = (int) $session->get('auth_user_fetched_at', 0);
        $stale     = now()->timestamp - $fetchedAt > 300; // 5 دقائق كاش

        // نجلب فقط إذا تغيّر التوكن أو مرّ وقت كافٍ
        if ($stale || $tokenHash !== $lastHash) {
            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(5)
                    ->retry(1, 200)
                    ->get(config('services.auth_api.url', env('AUTH_API_URL', 'https://auth.howplatform.net/api/user')));

                if ($response->ok()) {
                    $userData = $response->json() ?: [];
                    $userData['is_sales'] = (int)($userData['is_sales'] ?? 0);
                    $userData['is_admin'] = (int)($userData['is_admin'] ?? 0);

                    $session->put('auth_user', $userData);
                    $session->put('auth_user_fetched_at', now()->timestamp);
                    $session->put('auth_token_hash', $tokenHash);

                    $request->setUserResolver(fn () => new GenericUser($userData));
                } elseif ($response->status() === 401) {
                    // توكن غير صالح أو مُبطَل (جلسة واحدة): امسح المستخدم
                    $session->forget(['auth_user', 'auth_user_fetched_at', 'auth_token_hash']);
                }
            } catch (\Throwable $e) {
                // لا تُسقط الطلب عند فشل auth API؛ أكمل بآخر مستخدم معروف إن وُجد
            }
        }

        // اربط المستخدم من الكاش إن وُجد (يعمل auth()->user())
        if ($userData = $session->get('auth_user')) {
            $userData['is_sales'] = (int)($userData['is_sales'] ?? 0);
            $userData['is_admin'] = (int)($userData['is_admin'] ?? 0);
            $request->setUserResolver(fn () => new GenericUser($userData));
        }

        return $next($request);
    }
}
