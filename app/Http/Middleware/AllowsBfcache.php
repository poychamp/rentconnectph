<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowsBfcache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe()) {
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate, max-age=0');
        }

        return $response;
    }
}
