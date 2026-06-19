<?php

return [
    'allowed' => [
        'diploma' => [
            'label'          => 'الدبلوم',
            'api_base'       => env('DIPLOMA_API_BASE', 'https://diploma.howplatform.net'),
            'plans_endpoint' => '/api/pos/plans',
            'redeem_path'    => '/redeem',
            'prefix'         => 'DIP',
        ],
        'library' => [
            'label'          => 'المكتبة',
            'api_base'       => env('LIBRARY_API_BASE', 'https://library.howplatform.net'),
            'plans_endpoint' => '/api/pos/plans',
            'redeem_path'    => '/redeem',
            'prefix'         => 'LIB',
        ],
        'digital' => [
            'label'          => 'الأدوات الرقمية',
            'api_base'       => env('DIGITAL_API_BASE', 'https://tools.howplatform.net'),
            'plans_endpoint' => '/api/pos/plans',
            'redeem_path'    => '/redeem',
            'prefix'         => 'DIG',
        ],
    ],
];
