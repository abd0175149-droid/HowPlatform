<?php
// /app/Http/Middleware/VerifyApiToken.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Api-Key');
        if (! hash_equals(config('services.library.api_key'), (string) $key)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
