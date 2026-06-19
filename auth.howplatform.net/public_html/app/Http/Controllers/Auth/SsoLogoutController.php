<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\JwtHelper;
use Firebase\JWT\ExpiredException;
use Throwable;

class SsoLogoutController extends Controller
{
    public function logoutEverywhere(Request $request)
    {
        $token = $request->cookie('howplatform_token');
        $userId = null; $jti = null;

        if ($token) {
            try {
                $jwt = JwtHelper::verifyToken($token);
                $userId = (int)($jwt->sub ?? 0);
                $jti    = (string)($jwt->jti ?? '');
            } catch (ExpiredException $e) {
                // لا مشكلة
            } catch (Throwable $e) {}
        }

        if ($userId && $jti) {
            $row = DB::table('users')->select('current_jti')->where('id',$userId)->first();
            if ($row && $row->current_jti === $jti) {
                DB::table('users')->where('id',$userId)->update([
                    'current_jti'=>null,'current_jti_issued_at'=>null,'updated_at'=>now(),
                ]);
            }
        }

        // اخرج محليًا إن وُجدت سشن
        try { Auth::guard('web')->logout(); } catch (\Throwable $e) {}
        try { $request->session()->invalidate(); $request->session()->regenerateToken(); } catch (\Throwable $e) {}

        // احذف الكوكي على الدومين العام
        setcookie('howplatform_token','',[
            'expires'=>time()-3600,'path'=>'/','domain'=>'.howplatform.net',
            'secure'=>true,'httponly'=>true,'samesite'=>'None',
        ]);

        $redirect = $request->query('redirect') ?: 'https://howplatform.net';
        return redirect()->away($redirect)->with('success','تم تسجيل الخروج من جميع المنصات.');
    }
}
