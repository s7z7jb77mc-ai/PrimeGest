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

    'webhook_secret' => env('WEBHOOK_SECRET', ''),

    'netikash' => [
        'client_id' => env('NETIKASH_CLIENT_ID'),
        'client_secret' => env('NETIKASH_CLIENT_SECRET'),
        'base_url' => env('NETIKASH_BASE_URL', 'https://gateway.netikash.com'),
        'token_path' => env('NETIKASH_TOKEN_PATH', '/oauth/token'),
        'payment_path' => env('NETIKASH_PAYMENT_PATH', '/api/v1/payment/initiate'),
        'webhook_secret' => env('NETIKASH_WEBHOOK_SECRET'),
        'usd_to_cdf_rate' => (float) env('NETIKASH_USD_TO_CDF_RATE', 2800),
    ],

];
