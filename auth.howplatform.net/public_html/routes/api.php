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
        return response()->json([
            'id'        => $decoded->sub,
            'name'      => $decoded->name,
            'email'     => $decoded->email,
            'avatar'    => $decoded->avatar,
            'is_sales'   => $decoded->is_sales ?? 0,
            'is_admin'   => $decoded->is_admin ?? 0,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
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
