<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\JwtHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * ✅ GET /api/me
     * يرجع بيانات المستخدم الحالي إذا كانت جلسة SSO صالحة.
     */
    public function me(Request $request): JsonResponse
    {
        // 1️⃣ ابحث عن الكوكي المشتركة
        $token = $request->cookie('howplatform_token');
        if (!$token) {
            return response()->json(['status' => 'unauthorized', 'message' => 'Missing token'], 401);
        }

        // 2️⃣ تحقق من التوكن (المفتاح من البيئة عبر JwtHelper)
        try {
            $decoded = JwtHelper::verifyToken($token);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'unauthorized', 'message' => 'Invalid or expired token'], 401);
        }

        // 3️⃣ ابحث عن المستخدم
        $user = DB::table('users')
            ->select('id', 'name', 'email', 'avatar', 'is_admin', 'is_sales', 'last_login_at')
            ->where('id', $decoded->sub ?? 0)
            ->first();

        if (!$user) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // 4️⃣ أرجع المعلومات
        return response()->json([
            'status'  => 'ok',
            'user'    => $user,
            'token_iat' => $decoded->iat ?? null,
            'token_exp' => $decoded->exp ?? null,
        ]);
    }
}
