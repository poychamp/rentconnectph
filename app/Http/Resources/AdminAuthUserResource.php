<?php

namespace App\Http\Resources;

use App\Enums\AppRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sidebar / page-shell payload for the logged-in admin user.
 *
 * `permissions` mirrors the server-side gate model: super-admin gets `['*']`
 * because `Gate::before` in `AppServiceProvider::boot()` short-circuits
 * every ability check to true — so the UI must do the same and not try to
 * filter against an enumerated permission list (which is empty for super-admin
 * by design — see `RolePermissionSeeder` docblock). Other roles get the flat
 * list of permission names from spatie.
 */
class AdminAuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $roleName = $user->getRoleNames()->first();
        $isSuperAdmin = $roleName === AppRole::superAdmin()->value;

        return [
            'name'        => $user->name,
            'initials'    => $this->initials($user->name),
            'role_label'  => $roleName ? AppRole::from($roleName)->label : '',
            'permissions' => $isSuperAdmin
                ? ['*']
                : $user->getAllPermissions()->pluck('name')->all(),
        ];
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
