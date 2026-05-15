<?php

namespace Tests\Feature\Api\V1\Field;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function makeFieldUser(array $overrides = []): User
    {
        return User::factory()->field()->create(array_merge([
            'password' => Hash::make('correct-old-password'),
        ], $overrides));
    }

    private function issueFieldToken(User $user): string
    {
        return $user->createToken('field-android', ['field'])->plainTextToken;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'current_password'      => 'correct-old-password',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $overrides);
    }

    // =========================================================================
    // Auth (4)
    // =========================================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->patchJson(route('api.v1.field.profile.password'), $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        // User without the field role (no permission) but holding a field-ability
        // token. abilities:field middleware passes; in-controller
        // hasPermissionTo('listings.field-work', 'admin') denies.
        $user  = User::factory()->create([
            'password' => Hash::make('correct-old-password'),
        ]);
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload())
            ->assertForbidden();
    }

    // =========================================================================
    // Validation (5)
    // =========================================================================

    public function test_it_requires_current_password(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload([
                'current_password' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'current_password' => 'Please enter your current password.',
            ]);
    }

    public function test_it_rejects_wrong_current_password(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload([
                'current_password' => 'wrong-old-password',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'current_password' => 'Current password is incorrect.',
            ]);

        $this->assertTrue(Hash::check('correct-old-password', $user->fresh()->password));
    }

    public function test_it_requires_new_password(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload([
                'password'              => '',
                'password_confirmation' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'password' => 'Please enter a new password.',
            ]);
    }

    public function test_it_rejects_new_password_below_8_chars(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload([
                'password'              => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'password' => 'New password must be at least 8 characters.',
            ]);
    }

    public function test_it_rejects_password_confirmation_mismatch(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload([
                'password'              => 'new-password-123',
                'password_confirmation' => 'different-confirm-456',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'password' => 'New password confirmation does not match.',
            ]);
    }

    // =========================================================================
    // Persistence (2)
    // =========================================================================

    public function test_it_changes_password_and_returns_success_envelope(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_it_does_not_modify_name_when_only_updating_password(): void
    {
        $user  = $this->makeFieldUser(['name' => 'Stable Name']);
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), $this->validPayload());

        $this->assertSame('Stable Name', $user->fresh()->name);
    }

    // =========================================================================
    // Throttle (1)
    // =========================================================================

    public function test_it_throttles_after_10_updates_in_an_hour(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        // Burn 10 successful updates. Each persists a new password, so the
        // current_password value rotates each iteration to stay valid.
        $current = 'correct-old-password';
        for ($i = 0; $i < 10; $i++) {
            $next = "rotating-password-{$i}";
            $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->patchJson(route('api.v1.field.profile.password'), [
                    'current_password'      => $current,
                    'password'              => $next,
                    'password_confirmation' => $next,
                ])
                ->assertOk();
            $current = $next;
        }

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.password'), [
                'current_password'      => $current,
                'password'              => 'one-too-many-pass',
                'password_confirmation' => 'one-too-many-pass',
            ])
            ->assertStatus(429);
    }

    // =========================================================================
    // Middleware introspection (1)
    // =========================================================================

    public function test_it_lives_in_api_group_with_sanctum_guard_and_field_ability(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.profile.password');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
    }
}
