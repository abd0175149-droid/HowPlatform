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
    }
}
