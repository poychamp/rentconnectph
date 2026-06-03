<?php

namespace App\Http\Middleware\Admin;

use App\Enums\AppRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            return redirect()->route(
                $user->hasRole(AppRole::field()->value) ? 'field.dashboard' : 'admin.dashboard'
            );
        }

        return $next($request);
    }
}
