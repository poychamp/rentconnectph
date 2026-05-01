<?php

use App\Enums\AppPermission;
use App\Http\Controllers\Field;
use Illuminate\Support\Facades\Route;

// Guarded — auth:admin required for everything below. Field officers and
// super-admins both authenticate against the admin guard; the wall between
// /admin/* and /field/* surfaces is in the URL space + per-route permission
// middleware, NOT at the guard level.
Route::middleware('auth:admin')->group(function () {
    Route::get('/', [Field\DashboardController::class, 'index'])->name('dashboard');

    // Field-work scope — gated by `listings.field-work`. Super-admin passes via
    // Gate::before bypass; field officer has the explicit grant.
    Route::middleware('can:' . AppPermission::listingsFieldWork()->value)->group(function () {
        Route::get('listings', [Field\ListingController::class, 'index'])
            ->name('listings.index');

        Route::get('listings/{listing:uuid}/edit', [Field\ListingController::class, 'edit'])
            ->name('listings.edit');

        Route::put('listings/{listing:uuid}', [Field\ListingController::class, 'update'])
            ->name('listings.update');
    });
});
