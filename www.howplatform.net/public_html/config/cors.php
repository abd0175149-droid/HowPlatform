<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://howplatform.net',
        'https://diploma.howplatform.net',
        'https://tools.howplatform.net',
        'https://library.howplatform.net',
    ],
    'allowed_origins_patterns' => [
        // '#^https://([a-z0-9-]+\.)*howplatform\.net$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
