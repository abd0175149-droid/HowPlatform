<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\GscController; 
use App\Http\Controllers\POS\SalesDashboardController;
use App\Http\Controllers\POS\SalesPersonDashboardController;
use App\Http\Controllers\POS\VoucherIssueController;
use App\Http\Controllers\POS\SalesPointsController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\POS\SalesLimitsController;
use App\Http\Controllers\POS\SalesTransactionsController;
use App\Http\Controllers\POS\ClaimsController;
use App\Http\Controllers\POS\PaymentsController;
use App\Http\Controllers\Performance\PerformanceController;

Route::get('/performance',          [PerformanceController::class,'index'])->name('performance.index');
Route::get('/performance/tracking', [PerformanceController::class, 'trackingView'])->name('performance.tracking');
Route::get('/performance/reports',  [PerformanceController::class, 'reportsView'])->name('performance.reports');
Route::get('/performance/groups',   [PerformanceController::class, 'groupsView'])->name('performance.groups');


// ⬇️ حماية جميع مسارات POS
Route::middleware(['web', 'auth.token', 'pos.access'])
    ->prefix('pos')
    ->name('pos.')
    ->group(function () {
        Route::get('/dashboard', [SalesDashboardController::class, 'index'])->name('dashboard');
        Route::get('/sales/dashboard', [SalesPersonDashboardController::class, 'index'])
            ->name('sales.dashboard');
        // صفحة الإصدار (الواجهة)
        Route::get('/issue', [VoucherIssueController::class, 'index'])->name('issue');
        // جلب خطط منصة معينة (Proxy)
        Route::get('/api/{platform}/plans', [VoucherIssueController::class, 'plans'])->name('api.plans');
        // إصدار الفاوتشر: تخزين محليًا + إرسال للمنصة الفرعية
        Route::post('/api/vouchers', [VoucherIssueController::class, 'store'])->name('api.vouchers.store');
    });

Route::prefix('pos')->name('pos.')->middleware(['web','auth.token','pos.access'])->group(function () {

    // واجهة ويب (فورم)
    Route::get('/sales-points',                     [SalesPointsController::class, 'index'])->name('sales-points.index');
    Route::post('/sales-points',                    [SalesPointsController::class, 'store'])->name('sales-points.store');
    Route::delete('/sales-points/{id}',             [SalesPointsController::class, 'delete'])->name('sales-points.delete');
    // API JSON
    Route::get('/api/sales-points',                 [SalesPointsController::class, 'list'])->name('sales-points.list');
    Route::post('/api/sales-points',                [SalesPointsController::class, 'storeJson'])->name('sales-points.storeJson');
    Route::put('/api/sales-points/{id}',            [SalesPointsController::class, 'update'])->name('sales-points.update');
    Route::delete('/api/sales-points/{id}',         [SalesPointsController::class, 'deleteJson'])->name('sales-points.deleteJson');
    Route::delete('/api/sales-points/{id}/image',   [SalesPointsController::class, 'removeImage'])->name('sales-points.removeImage');
    Route::post('/sales-limits',                    [SalesLimitsController::class, 'upsert'])->name('sales-limits.upsert');
    Route::delete('/sales-limits/{userId}',         [SalesLimitsController::class, 'delete'])->name('sales-limits.delete');
    Route::post('/api/sales-transactions',          [SalesTransactionsController::class, 'store'])->name('sales-transactions.store');
    Route::post('/api/sales-transactions/{id}/adjust', [SalesTransactionsController::class, 'adjust'])->name('sales-transactions.adjust');
    Route::post('/api/sales-transactions/refund/preview', [SalesTransactionsController::class, 'refundPreview'])->name('sales-transactions.refundPreview');
    Route::post('/api/sales-transactions/adjust-invoice/preview',[SalesTransactionsController::class, 'adjustInvoicePreview'])->name('sales-transactions.adjustInvoicePreview');
    Route::get ('/claims/preview', [ClaimsController::class, 'preview'])->name('claims.preview'); // ?user_id=&from=&to=
    Route::post('/claims',         [ClaimsController::class, 'store'])->name('claims.store');     // JSON/Fetch
    Route::get ('/claims/{id}',    [ClaimsController::class, 'show'])->name('claims.show');       // عرض مبسّط
    Route::get ('/claims/user/{userId}', [ClaimsController::class, 'listByUser'])->name('claims.byUser');

    Route::post('/payments',       [PaymentsController::class, 'store'])->name('payments.store'); // تسجيل دفعة + تخصيص
    Route::get('/claims/{id}/print', [ClaimsController::class, 'print'])->name('claims.print');

});


Route::get('/media/sales-points/{filename}', [MediaController::class, 'salesPoint'])
    ->where('filename', '.+')
    ->name('media.sales-point');
Route::get('/download/sales-points/{filename}', [MediaController::class, 'downloadSalesPoint'])
    ->where('filename', '.+')
    ->name('media.sales-point.download');

// ↓ باقي المسارات كما هي (لم تتغير)
Route::get('/robots.txt', function () {
    $txt = "User-agent: *\nDisallow:\n\nSitemap: https://library.howplatform.net/sitemap.xml\n";
    return response($txt, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->withoutMiddleware(['auth','verified','throttle']);

Route::get('/ads.txt', function () {
    return response("google.com, pub-3572625518315562, DIRECT, f08c47fec0942fa0", 200)
           ->header('Content-Type', 'text/plain');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/services', [HomeController::class, 'services'])->name('services');
Route::get('/support', [HomeController::class, 'support'])->name('support');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/privacy', [HomeController::class, 'privacy'])->name('privacy');
Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');
Route::get('/platforms', [HomeController::class, 'platforms'])->name('platforms');

Route::get('/__flush', function () {
    Artisan::call('optimize:clear');
    Artisan::call('config:cache');
    return 'Flushed at '.now();
});

Route::get('/sitemap.xml', [SitemapController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemap-main.xml', [SitemapController::class, 'mainSitemap'])->name('sitemap.main');

Route::get('/gsc/connect',  [GscController::class, 'connect']);
Route::get('/gsc/callback', [GscController::class, 'callback']);
Route::match(['get','post'], '/gsc/submit', [GscController::class, 'submit']);

Route::get('/logout', function (\Illuminate\Http\Request $request) {
    $redirect = $request->query('redirect', url('/'));
    return view('logout-redirect', ['redirect' => $redirect]);
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
