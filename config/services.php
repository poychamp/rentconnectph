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

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
    ],

    'app_store' => [
        'ios_url' => 'https://apps.apple.com/app/rentconnectph/id6769903800',
        'ios_app_id' => '9Z8K9V5LK6.com.rentconnectph.app',
        'ios_app_store_id' => '6769903800',
        'android_url' => 'https://play.google.com/store/apps/details?id=ph.rentconnect.app',
        'android_package' => 'ph.rentconnect.app',
        'android_sha256_fingerprints' => [
            '0E:59:BA:F0:12:74:26:F0:9F:24:F0:6F:6A:C8:BF:70:BE:1A:C2:2E:4D:CE:A8:8B:F5:70:CE:F0:50:BD:0D:3B',
        ],
    ],

];
