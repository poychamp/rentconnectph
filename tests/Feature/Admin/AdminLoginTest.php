<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_rejects_empty_submit(): void
    {
        $response = $this->post(route('admin.login.attempt'), []);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'email'    => 'Incorrect email or password.',
            'password' => 'Incorrect email or password.',
        ]);
        $this->assertGuest('admin');
    }

    public function test_it_rejects_when_only_email_missing(): void
    {
        $response = $this->post(route('admin.login.attempt'), ['password' => 'secret']);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'email' => 'Incorrect email or password.',
        ]);
        $response->assertSessionDoesntHaveErrors(['password']);
    }

    public function test_it_rejects_when_only_password_missing(): void
    {
        $response = $this->post(route('admin.login.attempt'), ['email' => 'foo@bar.com']);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'password' => 'Incorrect email or password.',
        ]);
        $response->assertSessionDoesntHaveErrors(['email']);
    }

    public function test_it_rejects_invalid_credentials(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $this->assertSame('Incorrect email or password.', session('login_error'));
        $this->assertGuest('admin');
    }

    public function test_it_rejects_when_email_doesnt_exist(): void
    {
        $response = $this->post(route('admin.login.attempt'), [
            'email' => 'ghost@nope.com',
            'password' => 'whatever',
        ]);

        $response->assertRedirect();
        // Same message as wrong-password — never leak whether the email exists.
        $this->assertSame('Incorrect email or password.', session('login_error'));
        $this->assertGuest('admin');
    }

    public function test_it_logs_in_with_valid_credentials_and_redirects_to_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertTrue(Auth::guard('admin')->user()->is($admin));
    }

    public function test_it_does_not_persist_remember_token_without_remember_me(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_it_persists_remember_token_with_remember_me(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $this->assertNotNull($admin->fresh()->remember_token);
    }

    public function test_it_regenerates_session_on_successful_login(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->startSession();
        $sessionIdBefore = session()->getId();

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $this->assertNotSame($sessionIdBefore, session()->getId());
    }

    public function test_it_redirects_already_authenticated_admin_to_dashboard_without_reprocessing(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        // Already logged in as this admin
        $this->actingAs($admin, 'admin');

        // Try to log in again — even with totally invalid creds, should bounce to dashboard
        // without reprocessing (validation must not run, original auth stays intact)
        $response = $this->post(route('admin.login.attempt'), [
            'email'    => 'someone-else@example.com',
            'password' => 'wrong',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Auth::guard('admin')->user()->is($admin));
    }

    public function test_it_does_not_repopulate_password_field_after_failure(): void
    {
        $submittedPassword = 'my-secret-password-12345';
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => $submittedPassword,
        ]);

        $followUp = $this->get(route('admin.login'));

        $followUp->assertDontSee($submittedPassword);
    }

    public function test_it_throttles_after_5_failed_attempts(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        // First 5 failed attempts — all rejected with the standard "Incorrect" message
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('admin.login.attempt'), [
                'email'    => $admin->email,
                'password' => 'wrong-password',
            ]);
            $response->assertRedirect();
            $this->assertSame(
                'Incorrect email or password.',
                session('login_error'),
                "Attempt {$i} should fail with the standard message",
            );
        }

        // 6th attempt — throttled. Different message (mentions waiting / too many).
        $response = $this->post(route('admin.login.attempt'), [
            'email'    => $admin->email,
            'password' => 'wrong-password',
        ]);
        $response->assertRedirect();
        $errors = session('errors');
        $this->assertNotNull($errors, '6th attempt should have validation errors flashed');
        $this->assertTrue($errors->has('email'), 'Throttle error should attach to the email field');
        $this->assertMatchesRegularExpression(
            '/too many/i',
            $errors->first('email'),
            'Throttle message should mention "too many" — not "Incorrect email or password."',
        );

        // Even with CORRECT credentials, throttle still applies
        $response = $this->post(route('admin.login.attempt'), [
            'email'    => $admin->email,
            'password' => 'correct-password',
        ]);
        $this->assertGuest('admin');
    }

    public function test_it_resets_throttle_counter_on_successful_login(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'password' => Hash::make('correct-password'),
        ]);

        // 4 failed attempts (just under the threshold)
        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email'    => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        // Successful login — resets the throttle counter
        $response = $this->post(route('admin.login.attempt'), [
            'email'    => $admin->email,
            'password' => 'correct-password',
        ]);
        $response->assertRedirect(route('admin.dashboard'));

        // Log out so we can attempt again
        Auth::guard('admin')->logout();
        session()->forget('login_error');

        // Now do 5 more failed attempts — none should throttle (counter was reset)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('admin.login.attempt'), [
                'email'    => $admin->email,
                'password' => 'wrong-password',
            ]);
            $this->assertSame(
                'Incorrect email or password.',
                session('login_error'),
                "After reset, attempt {$i} should fail with standard message (not throttled yet)",
            );
        }
    }
}
