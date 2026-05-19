<?php

namespace Database\Seeders;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperAdmin(
            env('SUPER_ADMIN_EMAIL', 'admin@rentconnect.ph'),
            env('SUPER_ADMIN_PASSWORD'),
            'Super Admin',
            'SUPER_ADMIN_PASSWORD',
        );

        $this->seedSuperAdmin(
            env('SUPER_ADMIN_TWO_EMAIL'),
            env('SUPER_ADMIN_TWO_PASSWORD'),
            'Super Admin Two',
            'SUPER_ADMIN_TWO_PASSWORD',
        );
    }

    private function seedSuperAdmin(?string $email, ?string $password, string $name, string $envKeyLabel): void
    {
        if (blank($email) || blank($password)) {
            $this->command?->warn("{$envKeyLabel} (or matching email) not set — skipping admin user seed.");
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $role = Role::where('name', AppRole::superAdmin()->value)
            ->where('guard_name', AppGuard::admin()->value)
            ->firstOrFail();

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
