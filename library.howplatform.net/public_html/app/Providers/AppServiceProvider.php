<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // https في بيئة الإنتاج
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // 👇 ثبّت قوالب الترقيم الافتراضية (العادي والمبسّط)
        Paginator::defaultView('vendor.pagination.how');
        //Paginator::defaultSimpleView('vendor.pagination.how-simple');

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
