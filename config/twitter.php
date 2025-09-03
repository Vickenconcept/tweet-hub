<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Twitter API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for Twitter API settings.
    |
    */

    'mentions' => [
        'max_results' => env('TWITTER_MENTIONS_MAX_RESULTS', 100),
        'tweet_fields' => [
            'author_id', 'created_at', 'id', 'text'
        ],
        'expansions' => [
            'author_id'
        ],
        'user_fields' => [
            'id', 'name', 'username', 'profile_image_url'
        ],
    ],

    'rate_limiting' => [
        'max_retries' => env('TWITTER_MAX_RETRIES', 3),
        'base_retry_delay' => env('TWITTER_BASE_RETRY_DELAY', 5),
    ],

    'logging' => [
        'log_api_calls' => env('TWITTER_LOG_API_CALLS', true),
        'log_success' => env('TWITTER_LOG_SUCCESS', false),
        'log_errors' => env('TWITTER_LOG_ERRORS', true),
        'log_rate_limits' => env('TWITTER_LOG_RATE_LIMITS', true),
    ],
]; 