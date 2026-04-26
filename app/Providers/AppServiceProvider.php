<?php

namespace App\Providers;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use Illuminate\Support\Facades\Gate;
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
    }
}
