<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * يسمح بالدخول للبائعين (is_sales) أو المشرفين (is_admin).
 * يفترض أن SSO (AuthenticateWithToken) ملأ session('auth_user').
 */
class EnsureSalesAccess
{
    public function handle(Request $request, Closure $next)
    {
        $u       = session('auth_user');
        $isSales = is_array($u) ? (int) ($u['is_sales'] ?? 0) : 0;
        $isAdmin = is_array($u) ? (int) ($u['is_admin'] ?? 0) : 0;

        if ($isSales !== 1 && $isAdmin !== 1) {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json(['status' => 'fail', 'message' => 'Forbidden: sales access required.'], 403);
            }
            abort(403, 'هذه الصفحة مخصّصة للبائعين والمشرفين.');
        }

        return $next($request);
    }
}
