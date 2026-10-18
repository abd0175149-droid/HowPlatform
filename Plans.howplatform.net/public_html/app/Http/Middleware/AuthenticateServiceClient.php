<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * مصادقة المنصّات الداخلية عبر ترويسة X-Api-Key.
 * عند النجاح يضع slug العميل في attributes('service_client') ليستخدمه الكنترولر.
 */
class AuthenticateServiceClient
{
    public function handle(Request $request, Closure $next)
    {
        $provided = (string) $request->header('X-Api-Key', '');
        if ($provided === '') {
            return response()->json(['ok' => false, 'error' => 'missing_api_key'], 401);
        }

        $clients = (array) config('plans.clients', []);
        foreach ($clients as $slug => $key) {
            if (is_string($key) && $key !== '' && hash_equals($key, $provided)) {
                $request->attributes->set('service_client', (string) $slug);
                return $next($request);
            }
        }

        return response()->json(['ok' => false, 'error' => 'invalid_api_key'], 401);
    }
}
