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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    'zernio' => [
        'api_key' => env('ZERNIO_API_KEY'),
        'webhook_secret' => env('ZERNIO_WEBHOOK_SECRET'),
        'whatsapp_account_id' => env('ZERNIO_WHATSAPP_ACCOUNT_ID'),
        'bot_phone_number' => env('ZERNIO_BOT_PHONE_NUMBER'),
        'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api/v1'),
        'verification_template' => env('ZERNIO_WHATSAPP_VERIFICATION_TEMPLATE'),
        'alert_template' => env('ZERNIO_WHATSAPP_ALERT_TEMPLATE'),
        'template_language' => env('ZERNIO_WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        'timeout' => env('ZERNIO_TIMEOUT', 30),
        'connect_timeout' => env('ZERNIO_CONNECT_TIMEOUT', 15),
        'retry_attempts' => env('ZERNIO_RETRY_ATTEMPTS', 3),
    ],

    'whatsapp' => [
        'commands_per_hour' => (int) env('WHATSAPP_COMMANDS_PER_HOUR', 60),
    ],

];
