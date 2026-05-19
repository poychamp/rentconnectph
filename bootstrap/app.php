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
                ->prefix('auth')
                ->name('auth.')
                ->group(base_path('routes/auth.php'));

            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->prefix('field')
                ->name('field.')
                ->group(base_path('routes/field.php'));

            Route::middleware('api')
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'guest.admin' => \App\Http\Middleware\Admin\RedirectIfAuthenticatedAdmin::class,
            'bfcache' => \App\Http\Middleware\AllowsBfcache::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        $middleware->prependToGroup('web', \App\Http\Middleware\RedirectWwwToApex::class);

        $middleware->prependToGroup('api', \App\Http\Middleware\AddApiVersionHeaders::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\RequireJsonHeaders::class);

        // Override the default Authenticate middleware redirect: send all unauthed
        // hits to auth.login (`/auth/login`) — shared login surface for the admin
        // guard (admin + field roles, plus any future staff role on the same guard).
        // When a public-facing auth (broker / renter portal) lands, branch here.
        $middleware->redirectGuestsTo(fn () => route('auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
