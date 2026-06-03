<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireJsonHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isJson($request->header('Accept')) || ! $this->isJson($request->header('Content-Type'))) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 400);
        }

        return $next($request);
    }

    private function isJson(?string $header): bool
    {
        return $header !== null && str_contains(strtolower($header), 'application/json');
    }
}
