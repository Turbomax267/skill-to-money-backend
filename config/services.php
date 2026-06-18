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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'google_apps_script' => [
        'webhook_url' => env('GOOGLE_MAIL_WEBHOOK_URL'),
        'webhook_secret' => env('GOOGLE_MAIL_WEBHOOK_SECRET'),
        'timeout' => env('GOOGLE_MAIL_WEBHOOK_TIMEOUT', 15),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'timeout' => env('GEMINI_API_TIMEOUT', 20),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash-lite'),
        'timeout' => env('OPENROUTER_API_TIMEOUT', 20),
        'referer' => env('OPENROUTER_HTTP_REFERER'),
        'title' => env('OPENROUTER_APP_TITLE', 'SkillToMoney'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'timeout' => env('GROQ_API_TIMEOUT', 20),
    ],

    'ai' => [
        'local_fallback_enabled' => env('AI_LOCAL_FALLBACK_ENABLED', false),
    ],

    'peru_api' => [
        'base_url' => env('PERU_API_BASE_URL', 'https://peruapi.com'),
        'key' => env('PERU_API_KEY'),
        'timeout' => env('PERU_API_TIMEOUT', 8),
        'fallback_url' => env('PERU_API_FALLBACK_URL'),
    ],

    'culqi' => [
        'base_url' => env('CULQI_API_BASE_URL', 'https://api.culqi.com/v2'),
        'public_key' => env('CULQI_PUBLIC_KEY'),
        'private_key' => env('CULQI_PRIVATE_KEY'),
        'webhook_secret' => env('CULQI_WEBHOOK_SECRET'),
        'timeout' => env('CULQI_API_TIMEOUT', 20),
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

];
