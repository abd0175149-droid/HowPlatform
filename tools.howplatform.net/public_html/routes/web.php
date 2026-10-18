<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\ToolsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\PdfStampController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\GroupDistributionController;
use App\Http\Controllers\UserClassController;
use App\Http\Controllers\GroupingController;
use App\Http\Controllers\ScheduleToolController; 
use App\Http\Controllers\SpinnerController;
use App\Http\Controllers\AssignmentFormController;
use App\Http\Controllers\AssignmentFormV2Controller;
use App\Http\Controllers\Admin\ToolsAdminController;
/*
|==============================================================
| Tools Home
|==============================================================
*/
Route::get('/', [ToolsController::class, 'index'])->name('tools.index');

// صفحة تفاصيل الأداة (هبوط لمحركات البحث) + رابط انتقال متتبَّع (يزيد عدّاد الاستخدام ثم يحوّل)
Route::get('/t/{slug}',  [ToolsController::class, 'show'])->name('tools.show');
Route::get('/go/{slug}', [ToolsController::class, 'go'])->name('tools.go');

// API عام (JSON): قائمة الأدوات المفعّلة — تستهلكها منصّات أخرى (داشبورد طلاب الدبلوم)
Route::get('/api/tools', [ToolsController::class, 'api'])->name('tools.api');

/*
|==============================================================
| ID Card Tool
|==============================================================
*/
Route::prefix('tools/id-card')->name('tools.idcard.')->group(function () {
    Route::get('/',         [IdCardController::class, 'show'])->name('show');
    Route::get('/template', [PdfStampController::class, 'template'])->name('template');
    Route::get('/preview',  [PdfStampController::class, 'preview'])->name('preview');
    Route::post('/rate',    [IdCardController::class, 'rate'])->name('rate');
    Route::post('/stamp',   [PdfStampController::class, 'stamp'])->name('stamp');
});

/*
|==============================================================
| نموذج الاجابة Tool
|==============================================================
*/

Route::prefix('tools/assignment-form')->name('tools.assignform.')->group(function () {
    Route::get('/',           [AssignmentFormController::class, 'show'])->name('show');
    Route::get('/template',   [AssignmentFormController::class, 'template'])->name('template');
    Route::get('/preview',    [AssignmentFormController::class, 'preview'])->name('preview');
    Route::post('/stamp',     [AssignmentFormController::class, 'stamp'])->name('stamp');
    Route::post('/rate',      [AssignmentFormController::class, 'rate'])->name('rate');
});


Route::prefix('tools/assignment-form/v2')->name('tools.assignform.v2.')->group(function () {
    Route::get('/',         [AssignmentFormV2Controller::class,'index'])->name('index');
    Route::get('/meta',     [AssignmentFormV2Controller::class,'meta'])->name('meta');         // DDL + defaults
    Route::post('/schema',  [AssignmentFormV2Controller::class,'schema'])->name('schema');     // resolve template
    Route::get('/preview',  [AssignmentFormV2Controller::class,'preview'])->name('preview');   // PDF iframe
    Route::post('/submit',  [AssignmentFormV2Controller::class,'submit'])->name('submit');     // save submission
    Route::post('/stamp',   [AssignmentFormV2Controller::class,'stamp'])->name('stamp');       // generate PDF
    Route::post('/profile', [AssignmentFormV2Controller::class,'saveProfile'])->name('profile'); // remember info
    Route::post('/rate',    [AssignmentFormV2Controller::class,'rate'])->name('rate');         // اختياري (كما V1)
});
Route::middleware(['web','auth.sso'])
  ->prefix('my/pdf-studio')
  ->name('tools.pdfstudio.')
  ->group(function () {
      Route::get('/', fn() => view('tools.pdf_studio'))->name('show');
  });

/*
|==============================================================
| User Classes (صفوفي الدراسية)
|==============================================================
*/

Route::middleware(['web', 'auth.sso'])->prefix('my')->group(function () {
    Route::get('/classes',                                      [UserClassController::class, 'index'])->name('user.classes.index');
    Route::post('/classes',                                     [UserClassController::class, 'store'])->name('user.classes.store');
    Route::put('/classes/{id}',                                 [UserClassController::class, 'update'])->name('user.classes.update');
    Route::delete('/classes/{id}',                              [UserClassController::class, 'destroy'])->name('user.classes.destroy');
    Route::get('/classes/{id}/students',                        [UserClassController::class, 'studentsPage'])->name('user.classes.students');
    Route::post('/classes/{id}/students/preview',               [UserClassController::class, 'studentsPreview'])->name('user.classes.students.preview');
    Route::post('/classes/{id}/students/save',                  [UserClassController::class, 'studentsStore'])->name('user.classes.students.store');
    Route::patch('/classes/{id}/students/{studentId}',          [UserClassController::class, 'studentUpdate'])->name('user.classes.student.update');
    Route::delete('/classes/{id}/students/{studentId}',         [UserClassController::class, 'studentDestroy'])->name('user.classes.student.destroy');
    Route::delete('/classes/{id}/students',                     [UserClassController::class, 'studentsDestroyBulk'])->name('user.classes.students.destroyBulk');
    Route::post('/classes/{id}/students/{studentId}/_update',   [UserClassController::class,'studentUpdate'])->name('user.classes.student.update.post');
    Route::post('/classes/{id}/students/{studentId}/_delete',   [UserClassController::class,'studentDestroy'])->name('user.classes.student.destroy.post');
    Route::post('/classes/{id}/students/_bulk-delete',          [UserClassController::class,'studentsDestroyBulk'])->name('user.classes.students.destroyBulk.post');
});


Route::middleware(['web', 'auth.sso'])->prefix('my')->group(function () {
    Route::get('/groupings',                        [GroupingController::class, 'index'])->name('user.groupings.index');
    Route::get('/groupings/create',                 [GroupingController::class, 'create'])->name('user.groupings.create');
    Route::post('/groupings',                       [GroupingController::class, 'store'])->name('user.groupings.store');     
    Route::post('/groupings/quick',                 [GroupingController::class, 'quickStore'])->name('user.groupings.quick');
    Route::get('/groupings/{id}/constraints',       [GroupingController::class, 'constraints'])->name('user.groupings.constraints');
    Route::post('/groupings/{id}/constraints',      [GroupingController::class, 'constraintsStore'])->name('user.groupings.constraints.store');
    Route::post('/groupings/{id}/generate',         [GroupingController::class, 'generate'])->name('user.groupings.generate');
    Route::post('/groupings/{id}/finalize',         [GroupingController::class, 'finalize'])->name('user.groupings.finalize');
    Route::get('/groupings/{id}',                   [GroupingController::class, 'show'])->name('user.groupings.show');
    Route::post('/groupings/{id}/reshuffle',        [GroupingController::class, 'reshuffle'])->name('user.groupings.reshuffle');
    Route::get('/groupings/{id}/pdf',               [GroupingController::class, 'pdf'])->name('user.groupings.pdf');
    Route::delete('/groupings/{id}',                [GroupingController::class, 'destroy'])->name('user.groupings.destroy');
    Route::post('/groupings/{id}/delete',           [GroupingController::class, 'destroyPost'])->name('user.groupings.destroy.post');
    Route::get('/classes/{classId}/majors',         [GroupingController::class, 'classMajors'])->name('user.classes.majors');
    Route::post('/groupings/{id}/names',            [GroupingController::class, 'applyNames'])->name('user.groupings.applyNames');
});

Route::middleware(['web', 'auth.sso'])->prefix('my')->group(function () {
    Route::get('/spinners',                         [SpinnerController::class, 'index'])->name('user.spinners.index');
    Route::get('/spinners/create',                  [SpinnerController::class, 'create'])->name('user.spinners.create');
    Route::post('/spinners',                        [SpinnerController::class, 'store'])->name('user.spinners.store');
    Route::get('/spinners/{id}',                    [SpinnerController::class, 'show'])->name('user.spinners.show');
    Route::post('/spinners/{id}/items/add',         [SpinnerController::class, 'itemsAdd'])->name('user.spinners.items.add');
    Route::post('/spinners/{id}/items/toggle',      [SpinnerController::class, 'itemsToggle'])->name('user.spinners.items.toggle');
    Route::post('/spinners/{id}/items/clear',       [SpinnerController::class, 'itemsClear'])->name('user.spinners.items.clear');
    Route::post('/spinners/{id}/settings',          [SpinnerController::class, 'saveSettings'])->name('user.spinners.settings');
    Route::post('/spinners/{id}/spin',              [SpinnerController::class, 'spin'])->name('user.spinners.spin');
    Route::delete('/spinners/{id}',                 [SpinnerController::class, 'destroy'])->name('user.spinners.destroy');
    Route::post('/spinners/{id}/delete',            [SpinnerController::class, 'destroyPost'])->name('user.spinners.destroy.post');
    Route::post('/tools/spinner/rate',              [SpinnerController::class, 'rate'])->name('tools.spinner.rate');
});

Route::prefix('tools/schedule')->name('tools.schedule.')->group(function () {
    Route::get('/',                                 [ScheduleToolController::class, 'index'])->name('index');
    Route::get('/api/courses',                      [ScheduleToolController::class, 'courses']);
    Route::get('/api/sections',                     [ScheduleToolController::class, 'sections']);
    Route::get('/api/section',                      [ScheduleToolController::class, 'sectionDetails']);
    Route::get('/pdf',                              [ScheduleToolController::class, 'pdf'])->name('pdf');
    Route::post('/rate',                            [ScheduleToolController::class, 'rate'])->name('rate');
});

/*
|==============================================================
| Admin: إدارة الأدوات (مشرفون فقط)
|==============================================================
*/
Route::middleware(['web', 'auth.sso', 'auth.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.tools.index'))->name('home');
    Route::get('/tools',               [ToolsAdminController::class, 'index'])->name('tools.index');
    Route::get('/tools/create',        [ToolsAdminController::class, 'create'])->name('tools.create');
    Route::post('/tools',              [ToolsAdminController::class, 'store'])->name('tools.store');
    Route::get('/tools/{tool}/edit',   [ToolsAdminController::class, 'edit'])->name('tools.edit');
    Route::put('/tools/{tool}',        [ToolsAdminController::class, 'update'])->name('tools.update');
    Route::delete('/tools/{tool}',     [ToolsAdminController::class, 'destroy'])->name('tools.destroy');
    Route::post('/tools/{tool}/toggle',[ToolsAdminController::class, 'toggle'])->name('tools.toggle');
});

/*
|==============================================================
| SSO Login / Logout
|==============================================================
*/
Route::get('/login', function (Request $request) {
    if (session('auth_user')) return redirect()->route('tools.index');
    $redirect = $request->query('redirect', url()->previous() ?: route('tools.index'));
    return redirect('https://auth.howplatform.net/login?redirect=' . urlencode($redirect));
})->name('login');

Route::post('/logout', function (Request $request) {
    // 1) امسح جلسة لارافيل
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    // 2) ألغِ كوكي التوكن المشتركة (domain-level)
    Cookie::queue(Cookie::forget('howplatform_token', '/', '.howplatform.net', true, true, false, 'Lax'));
    // 3) رجوع لصفحة الأدوات
    return redirect()->route('tools.index');
})->name('logout');

/*
|==============================================================
| SEO
|==============================================================
*/
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', function () {
    $txt = "User-agent: *\nAllow: /\nSitemap: https://tools.howplatform.net/sitemap.xml\n";
    return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

/*
|==============================================================
| Utilities (Cache)
|==============================================================
*/
Route::middleware(['web', 'auth.sso'])->get('/clear-all-cache', function () {
    // مقصور على الأدمن فقط — كان مكشوفًا للعامة (تنفيذ أوامر Artisan)
    abort_unless((int) (session('auth_user')['is_admin'] ?? 0) === 1, 403);
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('route:clear');
    Artisan::call('route:cache');
    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    return response()->json(['status' => 'success', 'message' => 'تم مسح جميع أنواع الكاش']);
});


