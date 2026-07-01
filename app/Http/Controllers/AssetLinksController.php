<?php

namespace App\Http\Controllers;

class AssetLinksController extends Controller
{
    public function index()
    {
        $body = [
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('services.app_store.android_package'),
                    'sha256_cert_fingerprints' => config('services.app_store.android_sha256_fingerprints'),
                ],
            ],
        ];

        return response()->json($body, 200, ['Content-Type' => 'application/json']);
    }
}
