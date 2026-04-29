<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'guest.admin' => \App\Http\Middleware\Admin\RedirectIfAuthenticatedAdmin::class,
            'bfcache' => \App\Http\Middleware\AllowsBfcache::class,
        ]);

        // Override the default Authenticate middleware redirect: send all unauthed
        // hits to admin.login. Today, admin is the only route group using auth
        // middleware so this callback only ever fires for /admin paths anyway.
        // When a public-facing auth (broker / renter portal) lands, branch here.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
