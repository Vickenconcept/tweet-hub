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
]; 