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

];
