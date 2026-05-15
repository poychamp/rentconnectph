<?php

namespace App\Providers;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use App\Models\Listing;
use App\Models\Renter;
use App\Observers\ListingObserver;
use App\Observers\RenterObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole(AppRole::superAdmin()->value, AppGuard::admin()->value)
                ? true
                : null;
        });

        // Vapor's SignedStorageUrlController calls Gate::authorize('uploadFiles', ...).
        // Without an explicit definition, the gate denies (403) for everyone the
        // Gate::before bypass doesn't cover (i.e. non-super-admins). Authorize any
        // admin-guard authenticated user — the routes that mount Vapor's controller
        // already gate by `auth:admin` + per-permission middleware, so reaching the
        // gate check means the user has already cleared route-level authorization.
        Gate::define('uploadFiles', fn ($user) => $user !== null);

        RateLimiter::for('inquiry-submit', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('api-inquiry-submit', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('api-contact-send', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-update-name', function (Request $request) {
            return Limit::perHour(10)->by($request->user()->id);
        });

        RateLimiter::for('api-update-password', function (Request $request) {
            return Limit::perHour(10)->by($request->user()->id);
        });

        // Propagate Renter / Listing updates to child Inquiry Algolia
        // documents — only when the attributes that flow into Inquiry's
        // toSearchableArray actually changed. See observer classes.
        Renter::observe(RenterObserver::class);
        Listing::observe(ListingObserver::class);
    }
}
