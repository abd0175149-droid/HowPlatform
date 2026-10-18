<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('HowPlatform — Plans wallet service.');
})->purpose('Display an inspiring quote');

// دورة حياة الاشتراكات: تفعيل المجدول وانتهاء المنتهي يوميًا (Asia/Amman).
// يتطلّب تشغيل `php artisan schedule:run` كل دقيقة في الحاوية (أو استدعاء الأمر يدويًا/عبر كرون خارجي).
Schedule::command('subscriptions:lifecycle')->dailyAt('00:10')->timezone('Asia/Amman');
