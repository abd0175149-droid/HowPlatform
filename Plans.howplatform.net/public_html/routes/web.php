<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlansController;
use App\Http\Controllers\Admin\CardsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\PointPackagesController;
use App\Http\Controllers\Admin\SubscriptionsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\NotificationsController;
use App\Http\Controllers\Seller\CardIssueController;
use App\Http\Controllers\Seller\SalesPointsController;
use App\Http\Controllers\Seller\SalesLimitsController;
use App\Http\Controllers\Seller\SalesTransactionsController;
use App\Http\Controllers\Seller\SalesDashboardController;
use App\Http\Controllers\Seller\ClaimsController;
use App\Http\Controllers\Seller\PaymentsController;
use App\Http\Controllers\User;

// صفحة حالة
Route::get('/', fn () => response()->json([
    'service' => 'plans.howplatform.net',
    'status'  => 'ok',
]));

/*
|--------------------------------------------------------------------------
| SSO login / logout (عبر auth.howplatform.net المركزي)
|--------------------------------------------------------------------------
*/
Route::get('/login', function (Request $request) {
    if (session('auth_user')) {
        return redirect()->route('home');
    }
    $redirect = $request->query('redirect', route('home'));
    return redirect('https://auth.howplatform.net/login?redirect=' . urlencode($redirect));
})->name('login');

// موجِّه بعد الدخول حسب الدور: المشرف → لوحة الإدارة، البائع → إصدار بطاقة.
Route::middleware(['web', 'auth.sso'])->get('/home', function () {
    $u = session('auth_user');
    if ((int) ($u['is_admin'] ?? 0) === 1) {
        return redirect()->route('admin.dashboard');
    }
    if ((int) ($u['is_sales'] ?? 0) === 1) {
        return redirect()->route('seller.issue');
    }
    return redirect()->route('user.dashboard');
})->name('home');

// صفحة هبوط تفعيل بطاقة (وجهة QR code) — تتطلّب دخول SSO ثم تعرض نموذج تأكيد بالكود.
Route::middleware(['web', 'auth.sso'])->get('/redeem', [User\ProfileController::class, 'redeemLanding'])->name('redeem.landing');

Route::post('/logout', function (Request $request) {
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    Cookie::queue(Cookie::forget('howplatform_token', '/', '.howplatform.net', true, true, false, 'Lax'));
    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| لوحة الأدمن (SSO + is_admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth.sso', 'auth.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // الإشعارات (static قبل {notification})
    Route::get('/notifications',            [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all',  [NotificationsController::class, 'markAllRead'])->name('notifications.readAll');
    Route::post('/notifications/clear',     [NotificationsController::class, 'clear'])->name('notifications.clear');
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{notification}',     [NotificationsController::class, 'destroy'])->name('notifications.destroy');

    // الخطط + المزايا
    Route::get('/plans',                 [PlansController::class, 'index'])->name('plans.index');
    Route::get('/plans/create',          [PlansController::class, 'create'])->name('plans.create');
    Route::post('/plans',                [PlansController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit',     [PlansController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}',          [PlansController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}',       [PlansController::class, 'destroy'])->name('plans.destroy');
    Route::post('/plans/{plan}/toggle',  [PlansController::class, 'toggle'])->name('plans.toggle');
    Route::post('/plans/{plan}/features',[PlansController::class, 'addFeature'])->name('plans.features.add');
    Route::delete('/features/{feature}', [PlansController::class, 'deleteFeature'])->name('plans.features.delete');

    // البطاقات
    Route::get('/cards',                [CardsController::class, 'index'])->name('cards.index');
    Route::get('/cards/issue',          [CardsController::class, 'issue'])->name('cards.issue');
    Route::post('/cards',               [CardsController::class, 'store'])->name('cards.store');
    Route::get('/cards/export',         [CardsController::class, 'export'])->name('cards.export');
    Route::get('/cards/export-qr',      [CardsController::class, 'exportQrPdf'])->name('cards.export.qr');
    Route::get('/cards/vouchers',       [CardsController::class, 'vouchersBulk'])->name('cards.vouchers'); // جماعي (قبل {card})
    Route::get('/cards/redeem',         [CardsController::class, 'redeemForm'])->name('cards.redeem.form');
    Route::post('/cards/redeem',        [CardsController::class, 'redeem'])->name('cards.redeem');
    Route::get('/cards/{card}/voucher', [CardsController::class, 'voucher'])->name('cards.voucher');
    Route::post('/cards/{card}/toggle', [CardsController::class, 'toggle'])->name('cards.toggle');

    // حزم النقاط (سلّم التسعير)
    Route::get('/packages',               [PointPackagesController::class, 'index'])->name('packages.index');
    Route::post('/packages',              [PointPackagesController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}',     [PointPackagesController::class, 'update'])->name('packages.update');
    Route::post('/packages/{package}/toggle', [PointPackagesController::class, 'toggle'])->name('packages.toggle');
    Route::delete('/packages/{package}',  [PointPackagesController::class, 'destroy'])->name('packages.destroy');

    // قائمة الاشتراكات العامة + التقارير
    Route::get('/subscriptions',        [SubscriptionsController::class, 'index'])->name('subscriptions.index');
    Route::get('/reports',              [ReportsController::class, 'index'])->name('reports.index');

    // المستخدمون: اشتراكات + محفظة
    Route::get('/users/search',                     [UsersController::class, 'search'])->name('users.search'); // AJAX (قبل /users)
    Route::get('/users',                            [UsersController::class, 'index'])->name('users.index');
    Route::post('/users/grant',                     [UsersController::class, 'grantSubscription'])->name('users.grant');
    Route::post('/users/adjust',                    [UsersController::class, 'adjustWallet'])->name('users.adjust');
    Route::post('/subscriptions/{subscription}/extend', [UsersController::class, 'extendSubscription'])->name('users.sub.extend');
    Route::post('/subscriptions/{subscription}/cancel', [UsersController::class, 'cancelSubscription'])->name('users.sub.cancel');
});

/*
|--------------------------------------------------------------------------
| واجهات المستخدم (SSO) — كل مستخدم يرى محفظته واشتراكه وملفه فقط
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth.sso'])->prefix('user')->name('user.')->group(function () {
    // الواجهة الموحّدة الوحيدة للمستخدم = بروفايل أوث. هذه الصفحات تحوّل إليه.
    $hub = 'https://auth.howplatform.net/profile';
    Route::get('/dashboard',     fn () => redirect()->away($hub))->name('dashboard');
    Route::get('/wallet',        fn () => redirect()->away($hub . '?active=points'))->name('wallet');
    Route::get('/plans',         fn () => redirect()->away($hub . '?active=plans'))->name('plans');
    Route::get('/subscriptions', fn () => redirect()->away($hub . '?active=plans'))->name('subscriptions');
    Route::get('/profile',       fn () => redirect()->away($hub))->name('profile');

    // يبقى مسار تفعيل البطاقة (يصله المستخدم من QR code → /redeem landing → POST هنا)
    Route::post('/redeem',       [User\ProfileController::class, 'redeemCard'])->name('redeem');
});

/*
|--------------------------------------------------------------------------
| المبيعات (POS) — البائعون يُصدرون بطاقات Plans؛ الإدارة تدير نقاط البيع والسقوف
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth.sso'])->prefix('seller')->name('seller.')->group(function () {
    // البائع + المشرف: إصدار بطاقة + لوحة البائع الشخصية
    Route::middleware('auth.sales')->group(function () {
        Route::get('/issue',  [CardIssueController::class, 'index'])->name('issue');
        Route::post('/issue', [CardIssueController::class, 'store'])->name('issue.store');
        Route::get('/mine',   [SalesDashboardController::class, 'mine'])->name('mine');
        Route::get('/my-vouchers',         [CardIssueController::class, 'vouchers'])->name('cards.vouchers');
        Route::get('/cards/{card}/voucher', [CardIssueController::class, 'voucher'])->name('cards.voucher');
    });

    // المشرف فقط: لوحة المبيعات + إدارة نقاط البيع والسقوف + الحركات المحاسبية
    Route::middleware('auth.admin')->group(function () {
        Route::get('/dashboard',          [SalesDashboardController::class, 'index'])->name('dashboard');

        Route::get('/points',             [SalesPointsController::class, 'index'])->name('points');
        Route::post('/points',            [SalesPointsController::class, 'store'])->name('points.store');
        Route::put('/points/{id}',        [SalesPointsController::class, 'update'])->name('points.update');
        Route::delete('/points/{id}',     [SalesPointsController::class, 'delete'])->name('points.delete');
        Route::post('/limits',            [SalesLimitsController::class, 'upsert'])->name('limits.upsert');
        Route::delete('/limits/{userId}', [SalesLimitsController::class, 'delete'])->name('limits.delete');

        Route::post('/tx',                [SalesTransactionsController::class, 'store'])->name('tx.store');
        Route::post('/tx/{id}/adjust',    [SalesTransactionsController::class, 'adjust'])->name('tx.adjust');
        Route::post('/tx/preview',        [SalesTransactionsController::class, 'adjustInvoicePreview'])->name('tx.preview');

        // المطالبات والمدفوعات
        Route::get('/claims',             [ClaimsController::class, 'index'])->name('claims');
        Route::get('/claims/preview',     [ClaimsController::class, 'preview'])->name('claims.preview');
        Route::post('/claims',            [ClaimsController::class, 'store'])->name('claims.store');
        Route::get('/claims/{claim}',     [ClaimsController::class, 'show'])->name('claims.show');
        Route::get('/claims/{claim}/print', [ClaimsController::class, 'print'])->name('claims.print');
        Route::post('/payments',          [PaymentsController::class, 'store'])->name('payments.store');
    });
});
