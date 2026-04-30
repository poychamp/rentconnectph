<?php

namespace Database\Seeders;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class FieldUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('FIELD_USER_EMAIL', 'field@rentconnect.ph');
        $password = env('FIELD_USER_PASSWORD');
        $name     = env('FIELD_USER_NAME', 'Field Officer');

        if (blank($password)) {
            $this->command?->warn('FIELD_USER_PASSWORD not set — skipping field user seed.');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $role = Role::where('name', AppRole::field()->value)
            ->where('guard_name', AppGuard::admin()->value)
            ->firstOrFail();

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
