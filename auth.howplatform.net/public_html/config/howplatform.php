<?php
// config/howplatform.php
return [
    'platforms' => [
        'diploma' => [
            'name'      => 'منصة الدبلوم العالي',
            'base_url'  => env('DIPLOMA_BASE_URL', 'https://diploma.howplatform.net'),
            'schema'    => '/api/profile/schema', // يرجّع مخطط الحقول + القيم
            'save'      => '/api/profile',        // حفظ تعديلات المستخدم
        ],
        'library' => [
            'name'      => 'المكتبة الرقمية',
            'base_url'  => env('LIBRARY_BASE_URL', 'https://library.howplatform.net'),
            'schema'    => '/api/profile/schema',
            'save'      => '/api/profile',
        ],
        'tools' => [
            'name'      => 'الأدوات الرقمية',
            'base_url'  => env('TOOLS_BASE_URL', 'https://tools.howplatform.net'),
            'schema'    => '/api/profile/schema',
            'save'      => '/api/profile',
        ],
    ],
    // مهلة/مهل الشبكة
    'timeout' => 8, // ثوانٍ
];
