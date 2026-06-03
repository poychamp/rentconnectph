<?php

namespace Database\Factories;

use App\Enums\AppGuard;
use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a super admin (assigns super-admin role on admin guard).
     * Resets remember_token to null so remember-me behavior tests have a clean baseline.
     */
    public function superAdmin(): static
    {
        return $this->state(fn () => ['remember_token' => null])
            ->afterCreating(function (User $user) {
                $role = Role::firstOrCreate([
                    'name' => AppRole::superAdmin()->value,
                    'guard_name' => AppGuard::admin()->value,
                ]);

                $user->assignRole($role);
            });
    }

    /**
     * Indicate that the user is a field officer (assigns field role on admin guard).
     */
    public function field(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate([
                'name' => AppRole::field()->value,
                'guard_name' => AppGuard::admin()->value,
            ]);

            $user->assignRole($role);
        });
    }
}
