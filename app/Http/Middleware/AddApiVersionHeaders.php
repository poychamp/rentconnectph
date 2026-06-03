<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddApiVersionHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Min-Android-Consumer-Version', config('api.min_android_consumer_version'));
        $response->headers->set('X-Min-Android-Field-Version', config('api.min_android_field_version'));
        $response->headers->set('X-Min-iOS-Version', config('api.min_ios_version'));

        return $response;
    }
}
