<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AuthProfileNameTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guests_to_login(): void
    {
        $response = $this->put(route('auth.profile.name'), ['name' => 'Anything']);

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_requires_name(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.name'), ['name' => '']);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['name' => 'Please enter your name.'],
            null,
            'update-name'
        );
    }

    public function test_it_caps_name_at_255_characters(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.name'), ['name' => str_repeat('a', 256)]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['name' => 'Name must be 255 characters or fewer.'],
            null,
            'update-name'
        );
    }

    public function test_it_persists_name_and_redirects_with_flash(): void
    {
        $user = User::factory()->superAdmin()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.name'), ['name' => 'New Name']);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHas('success', 'Name updated.');
        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_it_does_not_modify_password_when_only_updating_name(): void
    {
        $user = User::factory()->superAdmin()->create([
            'name' => 'Old Name',
            'password' => Hash::make('keep-this-password'),
        ]);
        $oldHash = $user->password;

        $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.name'), ['name' => 'New Name']);

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_it_field_officer_can_update_their_own_name(): void
    {
        $user = User::factory()->field()->create(['name' => 'Old Field']);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.name'), ['name' => 'New Field']);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHas('success', 'Name updated.');
        $this->assertSame('New Field', $user->fresh()->name);
    }
}
