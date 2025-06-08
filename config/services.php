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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
        'proxy' => [
            'enabled' => env('TELEGRAM_PROXY_ENABLED', false),
            'address' => env('TELEGRAM_PROXY_ADDRESS'),
            'port' => env('TELEGRAM_PROXY_PORT'),
            'username' => env('TELEGRAM_PROXY_USERNAME'),
            'password' => env('TELEGRAM_PROXY_PASSWORD'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),
    ],

];
