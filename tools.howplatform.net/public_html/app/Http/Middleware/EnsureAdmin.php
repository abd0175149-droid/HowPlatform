<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = session('auth_user');
        $isAdmin = is_array($u) ? (int) ($u['is_admin'] ?? 0) : 0;
        abort_unless($isAdmin === 1, 403, 'هذه الصفحة مخصصة للمشرفين.');
        return $next($request);
    }
}
