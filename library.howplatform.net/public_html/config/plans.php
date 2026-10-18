<?php

/*
| ربط المكتبة بمنصّة النقاط (Plans): إنفاذ حصّة التحميلات اليومية
| (library/downloads_per_day: Basic 10 / Pro 25 / Elite 50) + حصّة مجانية
| لمن لا خطة له. الاتصال داخلي عبر شبكة Docker، ويفشل بأمان (fail-open).
|
| mode: off = السلوك القديم حرفيًا (لا فرض) | api = فرض الدخول + الحصص.
*/

return [
    'base_url' => rtrim(env('PLANS_BASEURL', 'http://plans/api/v1'), '/'),
    'api_key'  => env('PLANS_API_KEY'),
    'mode'     => env('PLANS_USAGE_MODE', 'off'),
    'timeout'  => (float) env('PLANS_TIMEOUT', 1.5),

    // الحصّة اليومية المجانية للمسجَّل الذي لا خطة له
    'free_daily_downloads' => (int) env('PLANS_FREE_DAILY_DOWNLOADS', 3),
];
