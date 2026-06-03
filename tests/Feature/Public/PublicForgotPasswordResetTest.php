<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class PublicForgotPasswordResetTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private const GENERIC_FAILURE = 'This password reset link is invalid or has expired. Please request a new one.';

    private function freshUserAndToken(): array
    {
        $user = User::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('old-password-123'),
        ]);
        $token = Password::createToken($user);

        return [$user, $token];
    }

    private function validPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'token'                 => $token,
            'email'                 => 'admin@example.com',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $overrides);
    }

    public function test_it_rejects_missing_token(): void
    {
        [$user, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, ['token' => '']))
            ->assertSessionHasErrors(['token' => self::GENERIC_FAILURE]);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_it_rejects_missing_email(): void
    {
        [, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, ['email' => '']))
            ->assertSessionHasErrors(['email' => 'Please enter a valid email address.']);
    }

    public function test_it_rejects_missing_password(): void
    {
        [, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, [
            'password'              => '',
            'password_confirmation' => '',
        ]))
            ->assertSessionHasErrors(['password' => 'Please choose a password.']);
    }

    public function test_it_rejects_password_confirmation_mismatch(): void
    {
        [, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, [
            'password_confirmation' => 'different-password',
        ]))
            ->assertSessionHasErrors(['password' => 'Password confirmation does not match.']);
    }

    public function test_it_rejects_password_below_min_length(): void
    {
        [, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, [
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]))
            ->assertSessionHasErrors(['password' => 'Password must be at least 8 characters.']);
    }

    public function test_it_rejects_invalid_token(): void
    {
        $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload('garbage-token-not-real'))
            ->assertSessionHasErrors(['email' => self::GENERIC_FAILURE]);
    }

    public function test_it_rejects_expired_token(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 10, 0, 0));
        [$user, $token] = $this->freshUserAndToken();

        Carbon::setTestNow(Carbon::create(2026, 5, 5, 11, 1, 0));

        $this->post('/reset-password', $this->validPayload($token))
            ->assertSessionHasErrors(['email' => self::GENERIC_FAILURE]);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_it_resets_password_and_redirects_to_login_with_flash(): void
    {
        [$user, $token] = $this->freshUserAndToken();

        $response = $this->post('/reset-password', $this->validPayload($token));

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHas('success', 'Password updated. Sign in.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(
            0,
            DB::table('password_reset_tokens')->where('email', $user->email)->count(),
            'token row should be deleted after successful reset'
        );
    }

    public function test_it_does_not_repopulate_password_field_on_failure(): void
    {
        [, $token] = $this->freshUserAndToken();

        $this->post('/reset-password', $this->validPayload($token, [
            'password_confirmation' => 'different',
        ]));

        $this->assertSame('admin@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
    }

    public function test_it_throttles_after_5_requests_in_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', [
                'token'                 => 'whatever',
                'email'                 => "spam{$i}@example.com",
                'password'              => 'a-password',
                'password_confirmation' => 'a-password',
            ]);
        }
        $this->post('/reset-password', [
            'token'                 => 'whatever',
            'email'                 => 'spam6@example.com',
            'password'              => 'a-password',
            'password_confirmation' => 'a-password',
        ])->assertStatus(429);
    }

    public function test_it_redirects_authed_admins_away_from_reset_route(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post('/reset-password', $this->validPayload('whatever'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_it_redirects_authed_field_officers_away_from_reset_route(): void
    {
        $field = User::factory()->field()->create();

        $this->actingAs($field, 'admin')
            ->post('/reset-password', $this->validPayload('whatever'))
            ->assertRedirect(route('field.dashboard'));
    }
}
