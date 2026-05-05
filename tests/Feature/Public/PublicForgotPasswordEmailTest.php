<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class PublicForgotPasswordEmailTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private const AMBIGUOUS_MESSAGE = "If an account with that email exists, we've sent a reset link.";

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_it_rejects_missing_email(): void
    {
        $this->post('/forgot-password', ['email' => ''])
            ->assertSessionHasErrors(['email' => self::AMBIGUOUS_MESSAGE.'.']);
        Notification::assertNothingSent();
    }

    public function test_it_rejects_invalid_email_format(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email' => self::AMBIGUOUS_MESSAGE.'..']);
        Notification::assertNothingSent();
    }

    public function test_it_returns_ambiguous_success_for_unknown_email_and_dispatches_nothing(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('success', self::AMBIGUOUS_MESSAGE.'...');
        Notification::assertNothingSent();
    }

    public function test_it_returns_ambiguous_success_for_known_email_and_dispatches_notification(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->post('/forgot-password', ['email' => 'admin@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('success', self::AMBIGUOUS_MESSAGE);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_it_throttles_after_5_requests_in_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "spam{$i}@example.com"])
                ->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'spam6@example.com'])
            ->assertStatus(429);
    }
}
