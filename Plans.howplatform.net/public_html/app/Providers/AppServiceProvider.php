<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // باجينيشن بنمط Bootstrap 5 (القوالب كلها Bootstrap RTL) بدل Tailwind الافتراضي
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // بيانات البانر لواجهة المستخدم (الرصيد + الخطّة الفعّالة) على كل صفحة حساب
        \Illuminate\Support\Facades\View::composer('layouts.user', function ($view) {
            $uid = (int) (session('auth_user')['id'] ?? 0);
            $balance = 0;
            $ent = ['has_plan' => false, 'plan' => null];
            if ($uid > 0) {
                try {
                    $balance = app(\App\Services\WalletService::class)->balance($uid);
                    $ent     = app(\App\Services\EntitlementService::class)->forUser($uid);
                } catch (\Throwable $e) {
                    // لا تُسقط الصفحة عند فشل جلب البيانات
                }
            }
            $view->with('accBalance', $balance)->with('accEnt', $ent);
        });

        // مشاركة إشعارات الأدمن (من جدول admin_notifications) مع قشرة اللوحة على كل صفحة
        \Illuminate\Support\Facades\View::composer('layouts.admin', function ($view) {
            $notifications = collect();
            $unread = 0;
            try {
                $notifications = \App\Models\AdminNotification::orderByDesc('id')->limit(10)->get();
                $unread = \App\Models\AdminNotification::whereNull('read_at')->count();
            } catch (\Throwable $e) {
                // لا تُسقط الصفحة عند فشل الإشعارات
            }
            $view->with('notifications', $notifications)->with('notifUnread', $unread);
        });
    }
}
