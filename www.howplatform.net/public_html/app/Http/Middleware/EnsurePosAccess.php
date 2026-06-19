<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePosAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // دالة الرفض: Redirect للواجهات، و 403 JSON لواجهات الـ API/AJAX
        $deny = function () use ($request) {
            if (
                $request->expectsJson() ||
                $request->wantsJson() ||
                $request->ajax() ||
                $request->is('pos/api/*')
            ) {
                return response()->json([
                    'status'  => 'fail',
                    'message' => 'Forbidden: POS access required.'
                ], 403);
            }

            return redirect()->to('/')->with('error', 'ليست لديك صلاحية دخول نقاط البيع.');
        };

        // غير مسجّل
        if (!$user) {
            return $deny();
        }

        $isSales = (int)($user->is_sales ?? 0);
        $isAdmin = (int)($user->is_admin ?? 0);

        // ليس Sales ولا Admin
        if ($isSales !== 1 && $isAdmin !== 1) {
            return $deny();
        }

        return $next($request);
    }
}
