<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class MeTest extends TestCase
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

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized();
    }

    public function test_it_returns_authenticated_user_payload_for_field_officer(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertExactJson([
                'user' => [
                    'uuid'  => $user->uuid,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => 'field',
                ],
            ]);
    }

    public function test_it_derives_role_from_current_token_abilities_not_user_role(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_it_lives_in_api_middleware_group_with_sanctum_guard(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.me');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
