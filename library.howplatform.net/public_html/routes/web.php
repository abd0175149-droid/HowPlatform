<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibrarySitemapController;
use Illuminate\Support\Facades\Log;

Route::get('/sitemap.xml', [LibrarySitemapController::class, 'sitemap'])->name('library.sitemap');


Route::get('/robots.txt', function () {
    $txt = "User-agent: *\nDisallow:\n\nSitemap: https://library.howplatform.net/sitemap.xml\n";
    return response($txt, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->withoutMiddleware(['auth','verified','throttle']);
Route::get('/ads.txt', function () {
    return response("google.com, pub-3572625518315562, DIRECT, f08c47fec0942fa0", 200)
           ->header('Content-Type', 'text/plain');
});

Route::pattern('grade', '[-a-z0-9]+'); // ضبط بسيط للشكل العام للـ slug


Route::get('/', [LibraryController::class, 'index'])
    ->name('library.index')
    ->middleware('log.visit');

Route::get('/library/{grade}', [LibraryController::class,'grade'])
    ->name('library.grade');
    
Route::get('/library/download/{file}', [LibraryController::class,'download'])
     ->whereNumber('file')
     ->name('library.download');
// (اختياري) تحويل أي روابط قديمة كانت تشير إلى /library → الجذر
Route::redirect('/library', '/');


// Fallback 404 أنيق
Route::fallback(function () {
return response()->view('errors.404', [], 404);
});
Route::get('/db-check', function () {
    try { \DB::connection()->getPdo(); return 'DB OK'; }
    catch (\Throwable $e) { return $e->getMessage(); }
});


Route::get('/library/{grade}/ajax', [LibraryController::class, 'gradeAjax'])
     ->name('library.grade.ajax');
Route::get('/preview/{file}', [LibraryController::class, 'preview'])
     ->name('library.preview');