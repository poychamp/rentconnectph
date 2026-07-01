<?php

namespace App\Http\Controllers;

class ManifestController extends Controller
{
    public function index()
    {
        $body = [
            'name' => 'RentConnectPH',
            'short_name' => 'RentConnectPH',
            'description' => 'Verified rental listings in Cagayan de Oro.',
            'start_url' => '/',
            'display' => 'standalone',
            'theme_color' => '#f97316',
            'background_color' => '#ffffff',
            'icons' => [
                ['src' => '/web-app-manifest-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/web-app-manifest-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
            'related_applications' => [
                [
                    'platform' => 'play',
                    'id' => config('services.app_store.android_package'),
                    'url' => config('services.app_store.android_url'),
                ],
            ],
        ];

        return response()->json($body, 200, ['Content-Type' => 'application/manifest+json']);
    }
}
