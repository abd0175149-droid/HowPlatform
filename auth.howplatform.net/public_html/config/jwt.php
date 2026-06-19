<?php
// config/jwt.php — single source of truth for JWT, read from environment.
// Values are baked at `config:cache` time so they survive cached config.
return [
    'secret' => env('JWT_SECRET'),
    'algo'   => env('JWT_ALGO', 'HS256'),
    'iss'    => env('JWT_ISS', 'auth.howplatform.net'),
    'aud'    => env('JWT_AUD', 'howplatform.net'),
    'ttl'    => (int) env('JWT_TTL_SECONDS', 2592000), // 30 days
];
