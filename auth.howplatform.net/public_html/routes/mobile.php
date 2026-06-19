<?php
// routes/mobile.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\StudentApiController;
use App\Http\Controllers\Mobile\CourseApiController;
use App\Http\Controllers\Mobile\ProfileApiController;
use App\Http\Controllers\Mobile\AppReleaseController;
use App\Http\Controllers\Mobile\MobileFileProxyController;
use App\Http\Controllers\Mobile\Support\ServiceRequestController;
use App\Http\Controllers\Mobile\Support\ServiceChatController;

Route::prefix('mobile')->group(function () {
    // Auth
    Route::post('login',            [AuthController::class, 'login']);
    Route::post('login/google',     [AuthController::class, 'loginGoogle']);
    Route::post('google/signin',    [AuthController::class, 'googleSignin'])->name('mobile.google.signin');

    Route::post('register',         [AuthController::class, 'register'])->name('mobile.register');
    Route::post('verify/send',      [AuthController::class, 'verifySend'])->name('mobile.verify.send');
    Route::post('verify/confirm',   [AuthController::class, 'verifyConfirm'])->name('mobile.verify.confirm');

    Route::post('password/forgot',  [AuthController::class, 'forgotPassword'])->name('mobile.password.forgot');
    Route::post('password/reset',   [AuthController::class, 'resetPassword'])->name('mobile.password.reset');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me',            [AuthController::class, 'me']);
        Route::post('logout',       [AuthController::class, 'logout']);
    });
});



Route::prefix('mobile/app')->group(function () {
    Route::get('/latest', [AppReleaseController::class, 'latest']);   
    Route::get('/download/{id}', [AppReleaseController::class, 'download']); 
});
Route::middleware(['api','auth:sanctum'])
    ->prefix('student/profile')
    ->group(function () {
        Route::get('/me',           [ProfileApiController::class, 'show']);
        Route::get('/meta',         [ProfileApiController::class, 'meta']);
        Route::patch('/auth',       [ProfileApiController::class, 'updateAuth']);
        Route::patch('/diploma',    [ProfileApiController::class, 'updateDiploma']);
        Route::post('/avatar',      [ProfileApiController::class, 'uploadAvatar']);
        Route::patch('/password',   [ProfileApiController::class, 'updatePassword']);
    });


Route::middleware(['api','auth:sanctum'])
    ->prefix('student')
    ->group(function () {
        Route::get('/my-courses',                           [CourseApiController::class,'userCourses']);
        Route::get('/courses/{courseOfferingId}/content',   [CourseApiController::class,'courseContent'])->whereNumber('courseOfferingId');
        Route::get('/courses/{courseOfferingId}/stats',     [CourseApiController::class,'courseStats'])->whereNumber('courseOfferingId');
        Route::get('/courses/{courseOfferingId}/search',    [CourseApiController::class,'searchInCourse'])->whereNumber('courseOfferingId');
        Route::get('/tasks/{taskId}/context',               [CourseApiController::class,'taskContext'])->whereNumber('taskId');
        Route::post('/courses/{courseOfferingId}/touch',    [CourseApiController::class,'updateLastAccess'])->whereNumber('courseOfferingId');
    });

Route::get('student/dashboard',     [StudentApiController::class, 'dashboard']);      
Route::get('student/courses',       [StudentApiController::class, 'courses']);        
Route::post('support-requests',     [StudentApiController::class, 'storeSupportRequest']); 


Route::middleware(['api','auth:sanctum'])
    ->prefix('mobile/files')
    ->group(function () {
        Route::get('{id}/check',    [MobileFileProxyController::class, 'check'])->whereNumber('id');
        Route::get('{id}/view',     [MobileFileProxyController::class, 'view'])->whereNumber('id');
        Route::get('{id}/download', [MobileFileProxyController::class, 'download'])->whereNumber('id');
    });


Route::prefix('mobile/support')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Requests
        Route::get('/meta',                  [ServiceRequestController::class, 'meta']);
        Route::post('/requests',             [ServiceRequestController::class, 'store']);
        Route::get('/requests',              [ServiceRequestController::class, 'index']);
        Route::get('/requests/{id}',         [ServiceRequestController::class, 'show']);
        Route::post('/requests/{id}/cancel', [ServiceRequestController::class, 'cancel']);

        // Chat meta
        Route::get('/top-meta', [ServiceChatController::class, 'topMeta']);

        // Chat
        Route::get('/requests/{id}/messages',                 [ServiceChatController::class, 'list']);
        Route::post('/requests/{id}/messages',                [ServiceChatController::class, 'send']);
        Route::patch('/requests/{id}/messages/{msgId}',       [ServiceChatController::class, 'edit']);
        Route::delete('/requests/{id}/messages/{msgId}',      [ServiceChatController::class, 'delete']);
        Route::post('/requests/{id}/read-up-to',              [ServiceChatController::class, 'readUpTo']);
        Route::get('/unread-counters',                        [ServiceChatController::class, 'unreadCounters']);
    });

// (اختياري) فحص سريع أن الملف محمّل:
Route::get('/mobile/ping', fn () => response()->json(['ok' => true]));

