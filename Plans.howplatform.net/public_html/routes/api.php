<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\CardsController;
use App\Http\Controllers\Api\EntitlementsController;
use App\Http\Controllers\Api\EarningController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\PlansController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\SubscriptionsController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\AccountController;

/*
|--------------------------------------------------------------------------
| Plans API (خدمة داخلية)
|--------------------------------------------------------------------------
| كل النقاط محميّة بمفتاح خدمة X-Api-Key. مسار عديم الحالة (لا جلسة/CSRF).
| المسارات الفعلية مسبوقة بـ /api تلقائيًا → /api/v1/wallet ...
*/
Route::middleware('auth.apikey')->prefix('v1')->group(function () {
    Route::get('/wallet',              [WalletController::class, 'balance']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/earn',   [WalletController::class, 'earn']);
    Route::post('/wallet/spend',  [WalletController::class, 'spend']);
    Route::post('/wallet/refund', [WalletController::class, 'refund']);

    Route::post('/cards/redeem',  [CardsController::class, 'redeem']);
    Route::get('/cards/verify',   [CardsController::class, 'verify']);

    Route::get('/entitlements',   [EntitlementsController::class, 'show']);

    // عدّاد استهلاك المزايا المحدودة بحصّة (فطين/تحميلات المكتبة …)
    Route::get('/usage',          [UsageController::class, 'show']);
    Route::post('/usage/consume', [UsageController::class, 'consume']);

    // تنظيف حساب مركزيًا عند حذفه من منصّة المصدر
    Route::post('/account/close', [AccountController::class, 'close']);

    // الكسب (مُرحَّل من الدبلوما)
    Route::post('/earn/login',             [EarningController::class, 'login']);
    Route::post('/earn/referral',          [EarningController::class, 'referral']);
    Route::post('/earn/referral/complete', [EarningController::class, 'referralComplete']);
    Route::get('/referral-code/resolve',   [EarningController::class, 'resolveCode']);
    Route::get('/referral-code',           [EarningController::class, 'referralCode']);

    // قراءة فقط (تستهلكها الدبلوما)
    Route::get('/wallet/stats',           [StatsController::class, 'walletStats']);
    Route::get('/referrals',              [StatsController::class, 'referrals']);
    Route::get('/plans',                  [PlansController::class, 'index']);
    Route::get('/catalog',                [CatalogController::class, 'index']);
    Route::get('/subscriptions',          [SubscriptionsController::class, 'index']);
    Route::get('/admin/subscription-stats', [AdminController::class, 'subscriptionStats']);
});
