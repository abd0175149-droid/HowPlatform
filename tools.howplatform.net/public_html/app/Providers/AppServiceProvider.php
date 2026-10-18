<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // شارة الخطة/النقاط في الهيدر (كاش جلسة 5 دقائق، تفشل بأمان → تُخفى)
        \Illuminate\Support\Facades\View::composer('partials.header', function ($view) {
            $badge = null;
            try {
                $uid = (int) (session('auth_user.id') ?? 0);
                if ($uid > 0) {
                    $cached = session('plans_badge');
                    if (is_array($cached) && ($cached['uid'] ?? 0) === $uid && (time() - ($cached['at'] ?? 0)) < 300) {
                        $badge = $cached['data'];
                    } else {
                        $badge = app(\App\Services\PlansClient::class)->badgeSummary($uid);
                        session(['plans_badge' => ['uid' => $uid, 'at' => time(), 'data' => $badge]]);
                    }
                }
            } catch (\Throwable $e) {
                $badge = null;
            }
            $view->with('plansBadge', $badge);
        });
    }
}
