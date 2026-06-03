<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum client versions
    |--------------------------------------------------------------------------
    |
    | Surfaced to mobile clients on every /api/v1/* response via the
    | App\Http\Middleware\AddApiVersionHeaders middleware. Bump the env values
    | when a server-side change breaks an older client; the app reads its own
    | bundle version and decides whether to nag-or-block based on the header.
    |
    */

    'min_android_consumer_version' => env('MIN_ANDROID_CONSUMER_VERSION', '1.0.0'),

    'min_android_field_version' => env('MIN_ANDROID_FIELD_VERSION', '1.0.0'),

    'min_ios_version' => env('MIN_IOS_VERSION', '1.0.0'),
];
