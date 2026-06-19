<?php
// public_html/routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\LibraryUploadsController;

Route::middleware('verify.api')->group(function () {
    // Upload (موجود أصلًا)
    Route::post('/uploads', [UploadController::class, 'store']);

    // Meta endpoints (جديدة)
    Route::get('/meta/grades', [MetaController::class, 'grades']);
    Route::get('/meta/grades/{grade}/subjects', [MetaController::class, 'subjectsByGrade']);
    Route::get('/meta/semesters', [MetaController::class, 'semesters']);
    Route::get('/meta/categories', [MetaController::class, 'categories']);
    // 
    Route::get('/uploads', [LibraryUploadsController::class, 'index']);
    Route::delete('/uploads/{id}', [LibraryUploadsController::class, 'destroy']);
    Route::post('/uploads/{id}/delete', [LibraryUploadsController::class, 'destroy']);

});

Route::middleware(['verify.api'])->get('/metrics/overview', function (\Illuminate\Http\Request $r) {
    try {
        $days = (int) $r->query('days', 30);
        if ($days < 1 || $days > 180) { $days = 30; }

        $from = now()->subDays($days)->startOfDay();
        $to   = now()->endOfDay();

        // نحصر الزيارات بالصفحة الرئيسية فقط
        $q = \Illuminate\Support\Facades\DB::table('visits')
               ->where('path', '/')
               ->whereBetween('created_at', [$from, $to]);

        $pageviews       = (clone $q)->count();
        $unique_sessions = (clone $q)->distinct('session_id')->count('session_id');
        $unique_ips      = (clone $q)->distinct('ip')->count('ip');

        // إجمالي الملفات
        $files_count     = \Illuminate\Support\Facades\DB::table('files')->count();

        // إجمالي مرّات التحميل (يمكن إبقاءه من جدول downloads)
        $downloads_total = \Illuminate\Support\Facades\DB::table('downloads')->count();
        // أو لو تعتمد على downloads_count في files:
        // $downloads_total = \Illuminate\Support\Facades\DB::table('files')->sum('downloads_count');

        return response()->json([
            'range'  => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'pageviews'        => (int) $pageviews,
                'unique_sessions'  => (int) $unique_sessions,
                'unique_ips'       => (int) $unique_ips,
                'files_count'      => (int) $files_count,      // 👈 جديد
                'downloads_total'  => (int) $downloads_total,
            ],
            'top_pages'  => [],
            'timeseries' => [],
        ], 200);
    } catch (\Throwable $e) {
        \Log::error('metrics.overview failed', ['err' => $e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine()]);
        return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
});
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Route::get('/metrics/ping', fn () => response()->json(['ok' => true]));

Route::get('/metrics/overview', function (Request $r) {
    $to   = Carbon::parse($r->query('to', now()))->endOfDay();
    $from = Carbon::parse($r->query('from', now()->subDays($r->query('days', 30))))->startOfDay();

    $q = DB::table('visits')->whereBetween('created_at', [$from, $to]);

    return response()->json([
        'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        'totals' => [
            'pageviews'       => (clone $q)->count(),
            'unique_sessions' => (clone $q)->distinct('session_id')->count('session_id'),
            'unique_ips'      => (clone $q)->distinct('ip')->count('ip'),
            'files_count'     => DB::table('files')->count(),
            'downloads_total' => DB::table('downloads')->count(),
        ],
        'top_pages'  => (clone $q)
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get(),
        'timeseries' => (clone $q)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as views, COUNT(DISTINCT session_id) as sessions, COUNT(DISTINCT ip) as visitors')
            ->groupBy('d')
            ->orderBy('d')
            ->get(),
    ]);
});
