<?php

namespace App\Http\Controllers;

class AppSiteAssociationController extends Controller
{
    public function index()
    {
        $appId = config('services.app_store.ios_app_id');

        $body = [
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => [$appId],
                        'components' => [
                            ['/' => '/admin/*', 'exclude' => true],
                            ['/' => '/field/*', 'exclude' => true],
                            ['/' => '/api/*', 'exclude' => true],
                            ['/' => '/privacy', 'exclude' => true],
                            ['/' => '/terms', 'exclude' => true],
                            ['/' => '/*'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($body, 200, ['Content-Type' => 'application/json']);
    }
}
