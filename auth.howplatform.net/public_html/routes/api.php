<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Api\SalesUsersController;
use App\Http\Controllers\Api\UserLookupController;
use App\Http\Controllers\Api\AuthController;

Route::get('/user', function (Request $request) {
    $token = $request->bearerToken() ?? $_COOKIE['howplatform_token'] ?? null;

    if (!$token) {
        return response()->json(['error' => 'Token not provided'], 401);
    }

    try {
        $decoded = JwtHelper::verifyToken($token);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }

    // فرض الجلسة الواحدة عبر jti: يرفض فقط عند وجود jti لا يطابق users.current_jti
    if (filter_var(env('JWT_ENFORCE_SINGLE_SESSION', false), FILTER_VALIDATE_BOOLEAN)) {
        $jti        = $decoded->jti ?? null;
        $allowNoJti = filter_var(env('JWT_LEGACY_ALLOW_NO_JTI', true), FILTER_VALIDATE_BOOLEAN);
        if ($jti === null) {
            if (!$allowNoJti) {
                return response()->json(['error' => 'Session invalidated'], 401);
            }
        } else {
            $current = \Illuminate\Support\Facades\DB::table('users')
                ->where('id', (int) ($decoded->sub ?? 0))
                ->value('current_jti');
            if ($current !== null && $current !== $jti) {
                return response()->json(['error' => 'Session superseded'], 401);
            }
        }
    }

    return response()->json([
        'id'        => $decoded->sub,
        'name'      => $decoded->name,
        'email'     => $decoded->email,
        'avatar'    => $decoded->avatar,
        'is_sales'  => $decoded->is_sales ?? 0,
        'is_admin'  => $decoded->is_admin ?? 0,
    ]);
});

Route::middleware('api')->group(function () {
    Route::get('/sales-users', [SalesUsersController::class, 'index']);
});

Route::prefix('lookup')->middleware(['verify.apikey'])->group(function () {

    Route::get('users/{id}/name', [UserLookupController::class, 'showName']);
    Route::get('users/names',  [UserLookupController::class, 'namesByIds']);
    Route::post('users/names', [UserLookupController::class, 'namesByIds']);
});

Route::get('/me', [AuthController::class, 'me']);
