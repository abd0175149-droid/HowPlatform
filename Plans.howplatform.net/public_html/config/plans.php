<?php

return [
    /*
    | عملاء الخدمة الداخليون: slug => مفتاح API سرّي (من .env).
    | المنصّات تُرسِل المفتاح في ترويسة X-Api-Key. المرحلة الأولى: مفاتيح ثابتة
    | (الترافيك داخلي خلف نفق Cloudflare). HMAC/التدوير يُضافان في مرحلة التصليب.
    */
    'clients' => array_filter([
        'diploma' => env('PLANS_KEY_DIPLOMA'),
        'tools'   => env('PLANS_KEY_TOOLS'),
        'auth'    => env('PLANS_KEY_AUTH'),
        'www'     => env('PLANS_KEY_WWW'),
        'library' => env('PLANS_KEY_LIBRARY'),
    ]),
];
