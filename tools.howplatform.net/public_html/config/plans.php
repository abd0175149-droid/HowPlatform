<?php

return [
    // عنوان أساس API المحفظة/الصلاحيات (داخلي عبر شبكة Docker)
    'baseUrl' => rtrim((string) env('PLANS_BASEURL', 'http://plans/api/v1'), '/'),
    'apiKey'  => (string) env('PLANS_API_KEY', ''),
    // off | shadow | api  — قراءة الصلاحيات من Plans
    'mode'    => (string) env('PLANS_ENTITLEMENTS_MODE', 'off'),
    'timeout' => (float) env('PLANS_TIMEOUT', 1.5),
];
