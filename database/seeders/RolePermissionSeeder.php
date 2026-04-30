<?php

namespace Database\Seeders;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Explicit role-to-guard mapping. Adding a guard to AppGuard does NOT
     * auto-create roles on it — each role assignment is intentional.
     *
     * Today: super-admin on admin guard only. When broker/renter portals land
     * on web/api guards, those roles get added here explicitly. Super-admin
     * stays admin-only — broker and renter portals must never grant super-admin.
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

    public function run(): void
    {
        $existingRoleIds = [];

        foreach ($this->rolesPerGuard() as $guard => $roleNames) {
            foreach ($roleNames as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
                $existingRoleIds[] = $role->id;
            }
        }

        Role::whereNotIn('id', $existingRoleIds)->delete();
    }
}
