<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function makeFieldUser(): User
    {
        return User::factory()->field()->create();
    }

    private function issueFieldToken(User $user, string $name = 'field-android'): string
    {
        return $user->createToken($name, ['field'])->plainTextToken;
    }

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->postJson(route('api.v1.auth.logout'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->postJson(route('api.v1.auth.logout'))
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_revokes_calling_token_and_returns_204_for_field_officer(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'field-android',
        ]);
    }

    public function test_it_only_revokes_the_calling_token_when_user_has_multiple_tokens(): void
    {
        $user   = $this->makeFieldUser();
        $tokenA = $this->issueFieldToken($user, 'device-a');
        $this->issueFieldToken($user, 'device-b');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'device-a',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'device-b',
        ]);
    }

    public function test_revoked_token_cannot_be_reused(): void
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        // Drop the cached RequestGuard user so request #2 re-resolves the bearer
        // against the (now empty) personal_access_tokens table — simulates the
        // per-request auth isolation that production gets for free.
        $this->app['auth']->forgetGuards();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson(route('api.v1.auth.logout'))
            ->assertUnauthorized();
    }

    public function test_it_lives_in_api_middleware_group_with_sanctum_guard(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.auth.logout');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
