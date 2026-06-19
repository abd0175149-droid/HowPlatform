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
     'google' => [
        // Socialite (ويب) يحتاج هذول:
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),

        // للموبايل والتحقق من id_token (aud whitelist):
        'client_ids' => array_filter([
            env('GOOGLE_CLIENT_ID'),
            env('GOOGLE_ANDROID_CLIENT_ID'),
            env('GOOGLE_IOS_CLIENT_ID'),
        ]),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    'userlookup' => [
        'key' => env('USERLOOKUP_API_KEY'),
    ],


    'auth' => [
    'secret_key' => env('AUTH_SECRET_KEY'),
],

    'fcm' => [
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH', base_path('howplatform-471514-firebase-adminsdk-fbsvc-b35be63f88.json')),
    ],

];
