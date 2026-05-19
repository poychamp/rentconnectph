<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serve Assets
    |--------------------------------------------------------------------------
    |
    | Root-level static files that Vapor's `ServeStaticAssets` middleware
    | should serve from S3 when the Lambda response would otherwise 404.
    | Browsers auto-fetch most of these from the document root, so they
    | can't be served via the asset() helper's hashed-prefix URLs.
    |
    | Add a path here only when (a) it lives at the bare root URL, AND
    | (b) it's a static file shipped via the `public/` directory.
    | Dynamic Laravel routes (e.g. /robots.txt, /sitemap.xml) don't need
    | to be listed — they 200 from Lambda directly.
    |
    */

    'serve_assets' => [
        'favicon.ico',
        'favicon.svg',
        'favicon-96x96.png',
        'apple-touch-icon.png',
        'web-app-manifest-192x192.png',
        'web-app-manifest-512x512.png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Robots.txt redirect
    |--------------------------------------------------------------------------
    |
    | Vapor's `RedirectStaticAssets` middleware 302-redirects `/robots.txt`
    | to the asset URL by default. We serve robots.txt via a Laravel route
    | (App\Http\Controllers\RobotsController) so the response is dynamic
    | (e.g. sitemap URL built from APP_URL). Disable the redirect so the
    | request reaches the route handler instead of bouncing to S3.
    |
    */

    'redirect_robots_txt' => false,

    /*
    |--------------------------------------------------------------------------
    | Favicon redirect
    |--------------------------------------------------------------------------
    |
    | Vapor's `RedirectStaticAssets` middleware 302-redirects `/favicon.ico`
    | to the asset URL (a CloudFront `*.cloudfront.net` host) by default.
    | Google's favicon-in-SERP rule requires the favicon to live on the same
    | host as the page that references it — a cross-domain redirect gets
    | rejected and no favicon shows in search results. Disabling the redirect
    | routes the request through `ServeStaticAssets` instead, which fetches
    | from S3 server-side and serves the bytes inline at the apex.
    |
    */

    'redirect_favicon' => false,

];
