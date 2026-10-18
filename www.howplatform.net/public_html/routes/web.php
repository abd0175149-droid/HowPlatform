<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\GscController;
use App\Http\Controllers\Performance\PerformanceController;

Route::get('/performance',          [PerformanceController::class,'index'])->name('performance.index');
Route::get('/performance/tracking', [PerformanceController::class, 'trackingView'])->name('performance.tracking');
Route::get('/performance/reports',  [PerformanceController::class, 'reportsView'])->name('performance.reports');
Route::get('/performance/groups',   [PerformanceController::class, 'groupsView'])->name('performance.groups');


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
