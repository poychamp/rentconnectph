<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AuthProfilePasswordTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guests_to_login(): void
    {
        $response = $this->put(route('auth.profile.password'), [
            'current_password' => 'x',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_requires_current_password(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => '',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['current_password' => 'Please enter your current password.'],
            null,
            'update-password'
        );
    }

    public function test_it_rejects_wrong_current_password(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'wrong-old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['current_password' => 'Current password is incorrect.'],
            null,
            'update-password'
        );
        $this->assertTrue(Hash::check('correct-old-password', $user->fresh()->password));
    }

    public function test_it_requires_new_password(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'correct-old-password',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['password' => 'Please enter a new password.'],
            null,
            'update-password'
        );
    }

    public function test_it_rejects_new_password_below_8_chars(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'correct-old-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['password' => 'New password must be at least 8 characters.'],
            null,
            'update-password'
        );
    }

    public function test_it_rejects_password_confirmation_mismatch(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'correct-old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-confirm-456',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHasErrors(
            ['password' => 'New password confirmation does not match.'],
            null,
            'update-password'
        );
    }

    public function test_it_resets_password_and_redirects_with_flash(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
            'remember_token' => 'old-remember-token-value',
        ]);
        $oldRememberToken = $user->remember_token;

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'correct-old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHas('success', 'Password updated.');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-password-123', $fresh->password));
        $this->assertNotSame($oldRememberToken, $fresh->remember_token);
        $this->assertNotNull($fresh->remember_token);
    }

    public function test_it_does_not_repopulate_password_fields_on_failure(): void
    {
        $user = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-old-password'),
        ]);

        $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'wrong-old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_it_field_officer_can_change_their_own_password(): void
    {
        $user = User::factory()->field()->create([
            'password' => Hash::make('field-old-password'),
        ]);

        $response = $this->actingAs($user, 'admin')
            ->from(route('auth.profile.show'))
            ->put(route('auth.profile.password'), [
                'current_password' => 'field-old-password',
                'password' => 'field-new-password-789',
                'password_confirmation' => 'field-new-password-789',
            ]);

        $response->assertRedirect(route('auth.profile.show'));
        $response->assertSessionHas('success', 'Password updated.');
        $this->assertTrue(Hash::check('field-new-password-789', $user->fresh()->password));
    }
}
