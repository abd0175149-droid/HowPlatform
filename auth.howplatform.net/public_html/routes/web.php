<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\SsoLogoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MediaController;

Route::get('/test', function () {
    $path = resource_path('views/test.php');
    if (!file_exists($path)) {
        abort(404, 'الملف test.php غير موجود داخل resources/views');
    }

    // 👇 قراءة الملف مباشرة وإرجاعه كمحتوى HTML
    return response()->file($path);
});
Route::get('/media/{path}', [MediaController::class, 'public'])
    ->where('path', '.*')
    ->name('media.public');

Route::get('/logout-everywhere', [SsoLogoutController::class, 'logoutEverywhere'])->name('sso.logout');

Route::get('auth/google', [GoogleController::class, 'redirect'])->name('google.login');
Route::get('auth/google/callback', [GoogleController::class, 'callback']);

Route::get('/', fn() => redirect()->route('login'));


Route::middleware('auth')->group(function () {
    // صفحة البروفايل الجديدة
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');

    // حفظ بيانات حساب auth (الاسم/الإيميل/الصورة)
    Route::patch('/profile', [ProfileController::class, 'updateAuth'])->name('profile.update');

    // حفظ بيانات الدبلوم (يُخزّن في قاعدة diploma عبر اتصال السرفر)
    Route::put('/profile/diploma', [ProfileController::class, 'updateDiploma'])->name('profile.diploma.update');
    
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');

    // استبدال بطاقة (نقاط/اشتراك) عبر منصّة النقاط
    Route::post('/profile/redeem', [ProfileController::class, 'redeemCard'])->name('profile.redeem');

    // تحديث كلمة المرور
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // حذف الحساب (اختياري، إن أردته)
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // جلب التخصصات حسب الجامعة
    Route::get('/profile/specialties/by-university', [ProfileController::class, 'getSpecialtiesByUniversity'])->name('profile.specialties.by-university');

    // توافق قديم للداشبورد
    Route::get('/dashboard', fn() => redirect()->route('profile.index'))->name('dashboard');
});


use Illuminate\Support\Facades\Artisan;

Route::get('/clear-all-cache', function () {
    // مسح كاش الكونفيج
    Artisan::call('config:clear');
    Artisan::call('config:cache');

    // مسح كاش الراوت
    Artisan::call('route:clear');
    Artisan::call('route:cache');

    // مسح كاش الفيوز
    Artisan::call('view:clear');

    // مسح كاش التطبيق
    Artisan::call('cache:clear');

    return response()->json(['status' => 'success', 'message' => 'تم مسح جميع أنواع الكاش']);
});



require __DIR__.'/auth.php';
