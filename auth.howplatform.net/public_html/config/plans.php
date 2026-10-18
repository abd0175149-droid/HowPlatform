<?php

/*
| ربط أوث بمنصّة النقاط (Plans) لقراءة رصيد النقاط + الاشتراك الفعّال وعرضها
| في صفحة البروفايل المركزية. قراءة فقط، عديمة الأثر، تفشل بأمان (fail-open).
|
| الاتصال داخلي عبر شبكة Docker (خدمة "plans") افتراضيًا؛ يُضبط عبر .env.
| المفتاح = PLANS_KEY_AUTH (نفسه المسجّل في Plans/.env تحت config/plans.php clients.auth)،
| يُرسَل في ترويسة X-Api-Key.
*/

return [
    'base_url' => rtrim(env('PLANS_API_URL', 'http://plans/api/v1'), '/'),
    'api_key'  => env('PLANS_API_KEY', env('PLANS_KEY_AUTH')),
    'timeout'  => (float) env('PLANS_API_TIMEOUT', 1.5),
    'enabled'  => filter_var(env('PLANS_API_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
