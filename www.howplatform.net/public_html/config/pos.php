<?php

return [
    'http_timeout' => env('POS_HTTP_TIMEOUT', 8),

    'platforms' => [
        'diploma' => [
            'label' => 'منصة الدبلوم',
            'base'  => env('DIPLOMA_BASE_URL', 'https://diploma.howplatform.net'),
            'plans' => '/api/v2/plans',
            'vouchers' => '/api/v2/vouchers',
            'verification' => '/api/v2/verification',
            'prefix' => 'DPL',
        ],
        'library' => [
            'label' => 'المكتبة الرقمية',
            'base'  => env('LIBRARY_BASE_URL', 'https://library.howplatform.net'),
            'plans' => '/api/plans',
            'vouchers' => '/api/vouchers',
            'verification' => '/api/verification',
            'prefix' => 'LIB',
        ],
        'digital' => [
            'label' => 'الأدوات الرقمية',
            'base'  => env('DIGITAL_BASE_URL', 'https://tools.howplatform.net'),
            'plans' => '/api/plans',
            'vouchers' => '/api/vouchers',
            'verification' => '/api/verification',
            'prefix' => 'DIG',
        ],
    ],
];
