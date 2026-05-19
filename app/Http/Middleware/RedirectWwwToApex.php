<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWwwToApex
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.')) {
            $apexHost = substr($host, 4);

            return redirect()->away(
                $request->getScheme().'://'.$apexHost.$request->getRequestUri(),
                301,
            );
        }

        return $next($request);
    }
}
