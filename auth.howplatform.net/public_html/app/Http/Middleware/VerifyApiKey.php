<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $provided = $request->header('X-Api-Key');
        $expected = config('services.userlookup.key');

        if (!$expected || $provided !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
