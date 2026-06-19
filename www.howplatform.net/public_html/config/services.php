<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],


    'diploma' => [
        'api_base' => env('DIPLOMA_API_BASE', 'https://diploma.howplatform.net'),
    ],
    'auth' => [
    'base_url'   => env('AUTH_BASE_URL', 'https://auth.howplatform.net/api'),
    'secret_key' => env('AUTH_SECRET_KEY'),
],



    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    // ليس ضروريًا، لأنك تبني redirect في الكود، لكن لا يضر:
    'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/gsc/callback'),
],

];
