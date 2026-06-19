<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
public function boot(): void
{
    // ... أي إعدادات أخرى

    Route::prefix('api')
        ->middleware('api')
        ->group(base_path('routes/api.php'));

    // ✅ تسجيل مسارات الموبايل
    Route::prefix('api')
        ->middleware('api')
        ->group(base_path('routes/mobile.php'));
}
}