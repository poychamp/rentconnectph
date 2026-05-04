<?php

namespace Database\Seeders;

use App\Enums\AppGuard;
use App\Enums\AppPermission;
use App\Enums\AppRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role + permission seeder.
 *
 * IMPORTANT: super-admin is intentionally NOT listed in `permissionsPerRole`.
 * The `Gate::before` bypass in `App\Providers\AppServiceProvider::boot()`
 * grants super-admins every ability — no explicit grants needed. When you
 * add a new permission, list it in `permissionsPerGuard` + grant to any
 * non-super-admin roles that need it. Super-admin gets it automatically.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Explicit role-to-guard mapping. Adding a guard to AppGuard does NOT
     * auto-create roles on it — each role assignment is intentional.
     *
     * Today: super-admin + field on admin guard only. When broker/renter
     * portals land on web/api guards, those roles get added here explicitly.
     * Super-admin stays admin-only.
     */
    protected function rolesPerGuard(): array
    {
        return [
            AppGuard::admin()->value => [
                AppRole::superAdmin()->value,
                AppRole::field()->value,
            ],
        ];
    }

    /**
     * Permissions per guard. Coarse-grained — one permission per coherent
     * capability bundle, not per CRUD action. `users.manage` and
     * `system.admin` are reserved (no consumers yet); listed here so they
     * exist when their consumers land.
     */
    protected function permissionsPerGuard(): array
    {
        return [
            AppGuard::admin()->value => [
                AppPermission::adminAccess()->value,       // /admin/* surface entry — desk-team Dashboard + future admin-only pages; field officers don't get this
                AppPermission::listingsManage()->value,    // desk-team scope: queue, verify, deactivate, reject, restore, reopen, feature, assign field officer
                AppPermission::listingsFieldWork()->value, // field-officer scope: visit updates, field photos, own assignments (future UI)
                AppPermission::amenitiesManage()->value,   // amenity catalog CRUD; super-admin only via Gate::before, no role grants
                AppPermission::inquiriesManage()->value,   // calls-team queue + handover; super-admin only via Gate::before, no role grants (calls-team role lands later)
                AppPermission::usersManage()->value,       // reserved
                AppPermission::systemAdmin()->value,       // reserved
            ],
        ];
    }

    /**
     * Per-guard, per-role permission grants. Super-admin is omitted — the
     * Gate::before bypass in AppServiceProvider grants super-admins every
     * ability without needing explicit permission rows.
     */
    protected function permissionsPerRole(): array
    {
        return [
            AppGuard::admin()->value => [
                AppRole::field()->value => [
                    AppPermission::listingsFieldWork()->value,
                ],
            ],
        ];
    }

    public function run(): void
    {
        $existingPermissionIds = [];
        $existingRoleIds = [];

        foreach ($this->permissionsPerGuard() as $guard => $permissionNames) {
            foreach ($permissionNames as $name) {
                $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                $existingPermissionIds[] = $perm->id;
            }
        }

        foreach ($this->rolesPerGuard() as $guard => $roleNames) {
            foreach ($roleNames as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
                $existingRoleIds[] = $role->id;

                $grantedNames = $this->permissionsPerRole()[$guard][$roleName] ?? [];
                $grantedPermissions = Permission::where('guard_name', $guard)
                    ->whereIn('name', $grantedNames)
                    ->get();
                $role->syncPermissions($grantedPermissions);
            }
        }

        Role::whereNotIn('id', $existingRoleIds)->delete();
        Permission::whereNotIn('id', $existingPermissionIds)->delete();
    }
}
