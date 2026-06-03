<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldLoginTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private const AUTH_FAILURE_MESSAGE = "Incorrect email or password, or this account isn't authorized for the field app.";

    private function makeFieldUser(array $overrides = []): User
    {
        return User::factory()->field()->create(array_merge([
            'email'    => 'marco@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email'    => 'marco@example.com',
            'password' => 'correct-horse-battery-staple',
        ], $overrides);
    }

    public function test_it_rejects_missing_email(): void
    {
        $this->postJson(route('api.v1.auth.field-login'), $this->payload(['email' => null]))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_invalid_email_format(): void
    {
        $this->postJson(route('api.v1.auth.field-login'), $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_missing_password(): void
    {
        $this->postJson(route('api.v1.auth.field-login'), $this->payload(['password' => null]))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_unknown_email(): void
    {
        $this->postJson(route('api.v1.auth.field-login'), $this->payload(['email' => 'ghost@example.com']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_wrong_password_for_known_field_user(): void
    {
        $this->makeFieldUser();

        $this->postJson(route('api.v1.auth.field-login'), $this->payload(['password' => 'wrong']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_rejects_correct_credentials_for_super_admin(): void
    {
        User::factory()->superAdmin()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $this->postJson(route('api.v1.auth.field-login'), [
            'email'    => 'admin@example.com',
            'password' => 'correct-horse-battery-staple',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_issues_token_and_returns_user_payload_for_field_officer_with_correct_credentials(): void
    {
        $user = $this->makeFieldUser();

        $response = $this->postJson(route('api.v1.auth.field-login'), $this->payload());

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['uuid', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.uuid', $user->uuid)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'field');

        $token = $response->json('token');
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^\d+\|[A-Za-z0-9]{40,}$/', $token);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $user->id,
            'name'           => 'field-android',
        ]);

        $row = DB::table('personal_access_tokens')->first();
        $this->assertSame(['field'], json_decode($row->abilities, true));
    }

    public function test_it_uses_device_name_as_token_name_when_provided(): void
    {
        $user = $this->makeFieldUser();

        $this->withHeaders(['X-Device-Name' => 'Pixel 7 — Marco'])
            ->postJson(route('api.v1.auth.field-login'), $this->payload())
            ->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'Pixel 7 — Marco',
        ]);
    }

    public function test_it_falls_back_to_default_name_when_device_name_header_is_empty(): void
    {
        $user = $this->makeFieldUser();

        $this->withHeaders(['X-Device-Name' => ''])
            ->postJson(route('api.v1.auth.field-login'), $this->payload())
            ->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'field-android',
        ]);
    }

    public function test_it_throttles_after_5_failed_attempts_via_email_aware_counter(): void
    {
        $this->makeFieldUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.auth.field-login'), $this->payload(['password' => 'wrong']))
                ->assertStatus(422)
                ->assertJsonPath('errors.email.0', self::AUTH_FAILURE_MESSAGE);
        }

        $response = $this->postJson(route('api.v1.auth.field-login'), $this->payload(['password' => 'wrong']));

        $response->assertStatus(422);

        $this->assertMatchesRegularExpression(
            '/Too many login attempts\. Please try again in \d+ seconds\./',
            $response->json('errors.email.0')
        );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_does_not_repopulate_password_field_in_response_errors(): void
    {
        $this->makeFieldUser();

        $response = $this->postJson(route('api.v1.auth.field-login'), $this->payload(['password' => 'wrong']));

        $response
            ->assertStatus(422)
            ->assertJsonMissingPath('errors.password');
    }

    public function test_it_lives_in_api_middleware_group_without_csrf_or_session(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.auth.field-login');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
