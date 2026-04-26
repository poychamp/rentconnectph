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
        $email = env('SUPER_ADMIN_EMAIL', 'admin@rentconnect.ph');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('SUPER_ADMIN_PASSWORD not set — skipping admin user seed.');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => Hash::make($password)],
        );

        $role = Role::where('name', AppRole::superAdmin()->value)
            ->where('guard_name', AppGuard::admin()->value)
            ->firstOrFail();

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
