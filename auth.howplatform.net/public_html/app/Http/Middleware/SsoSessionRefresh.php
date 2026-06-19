<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\JwtHelper;
use Firebase\JWT\ExpiredException;
use Throwable;

class SsoSessionRefresh
{
    /** مدة الخمول للكوكي (تتجدد تلقائياً) */
    private int $idleTtl;

    public function __construct()
    {
        $this->idleTtl = (int) env('SSO_IDLE_TTL_SECONDS', 2592000); // 30 days
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $token = $request->cookie('howplatform_token');

        // لو السشن مفقودة لكن كوكي SSO صالحة: أعد تهيئة السشن (rehydrate)
        if (!Auth::check() && $token) {
            try {
                $jwt = JwtHelper::verifyToken($token);
                $userId = (int)($jwt->sub ?? 0);
                $jti    = (string)($jwt->jti ?? '');

                if ($userId > 0 && $jti !== '') {
                    $row = DB::table('users')->select('id','current_jti')->where('id',$userId)->first();
                    if ($row && $row->current_jti === $jti) {
                        Auth::loginUsingId($userId, false);
                        // نضمن وجود سشن و CSRF قبل المرور على VerifyCsrfToken
                        $request->session()->migrate(true);
                        $request->session()->regenerateToken();

                        // لقطة خفيفة في السشن
                        $u = Auth::user();
                        $request->session()->put('auth_user', [
                            'id'=>$u->id,'name'=>$u->name,'email'=>$u->email,
                            'is_admin'=>(int)($u->is_admin ?? 0),
                            'is_sales'=>(int)($u->is_sales ?? 0),
                            'avatar'=>(string)($u->avatar ?? ''),
                        ]);
                    }
                }
            } catch (ExpiredException $e) {
                // انتهت التوكن — نتجاهل
            } catch (Throwable $e) {
                // parsing/verify — نتجاهل
            }
        }

        $response = $next($request);

        // جدّد الكوكي (خروج عند الخمول)
        if ($token && $this->idleTtl > 0) {
            setcookie('howplatform_token', $token, [
                'expires'  => time() + $this->idleTtl,
                'path'     => '/',
                'domain'   => '.howplatform.net',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'None',
            ]);
        }

        return $response;
    }

    private function shouldBypass(Request $request): bool
    {
        $p = $request->path();
        return preg_match('#^(login|logout|auth/.*|_debugbar|telescope|horizon)/?#i', $p) === 1;
    }
}
