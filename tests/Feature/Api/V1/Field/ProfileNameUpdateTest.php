<?php

namespace Tests\Feature\Api\V1\Field;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ProfileNameUpdateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function makeFieldUser(): User
    {
        return User::factory()->field()->create();
    }

    private function issueFieldToken(User $user): string
    {
        return $user->createToken('field-android', ['field'])->plainTextToken;
    }

    // =========================================================================
    // Auth (4)
    // =========================================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->patchJson(route('api.v1.field.profile.name'), ['name' => 'Anything'])
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'Anything'])
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'Anything'])
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        // User without the field role (no permission) but holding a field-ability
        // token. abilities:field middleware passes (token has the ability);
        // in-controller hasPermissionTo('listings.field-work', 'admin') denies.
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'Anything'])
            ->assertForbidden();
    }

    // =========================================================================
    // Validation (2)
    // =========================================================================

    public function test_it_requires_name(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Please enter your name.',
            ]);
    }

    public function test_it_caps_name_at_255_characters(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Name must be 255 characters or fewer.',
            ]);
    }

    // =========================================================================
    // Persistence (3)
    // =========================================================================

    public function test_it_persists_name_and_returns_success_envelope(): void
    {
        $user  = User::factory()->field()->create(['name' => 'Old Name']);
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'New Name'])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_it_does_not_modify_password_when_only_updating_name(): void
    {
        $user = User::factory()->field()->create([
            'name'     => 'Old Name',
            'password' => Hash::make('keep-this-password'),
        ]);
        $token   = $this->issueFieldToken($user);
        $oldHash = $user->password;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'New Name']);

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_it_only_updates_the_token_owners_name_not_someone_elses(): void
    {
        $owner = User::factory()->field()->create(['name' => 'Owner Old']);
        $other = User::factory()->field()->create(['name' => 'Other Untouched']);
        $token = $this->issueFieldToken($owner);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.profile.name'), ['name' => 'Owner New'])
            ->assertOk();

        $this->assertSame('Owner New',       $owner->fresh()->name);
        $this->assertSame('Other Untouched', $other->fresh()->name);
    }

    // =========================================================================
    // Middleware introspection (1)
    // =========================================================================

    public function test_it_lives_in_api_group_with_sanctum_guard_and_field_ability(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.profile.name');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
    }
}
