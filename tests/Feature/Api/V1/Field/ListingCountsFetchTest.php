<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingCountsFetchTest extends TestCase
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

    private function actAsFieldOfficer(): User
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);
        $this->withHeaders(['Authorization' => "Bearer {$token}"]);
        return $user;
    }

    private function queuedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to'  => $officer->id,
            'visited_at'   => null,
        ], $overrides));
    }

    // ===========================================================
    // Auth
    // ===========================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.field.listings.counts'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.counts'))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.counts'))
            ->assertForbidden();
    }

    // ===========================================================
    // Counts / scope
    // ===========================================================

    public function test_it_returns_zero_when_officer_has_no_queued_listings(): void
    {
        $this->actAsFieldOfficer();

        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertOk()
            ->assertExactJson(['queued' => 0]);
    }

    public function test_it_counts_queued_listings_assigned_to_current_officer(): void
    {
        $marco = $this->actAsFieldOfficer();

        $this->queuedListingFor($marco);
        $this->queuedListingFor($marco);
        $this->queuedListingFor($marco);

        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertOk()
            ->assertExactJson(['queued' => 3]);
    }

    public function test_it_excludes_listings_assigned_to_other_officers(): void
    {
        $marco = $this->actAsFieldOfficer();
        $other = $this->makeFieldUser();

        $this->queuedListingFor($marco);
        $this->queuedListingFor($other);
        $this->queuedListingFor($other);

        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertOk()
            ->assertExactJson(['queued' => 1]);
    }

    public function test_it_excludes_listings_with_queue_status_visited(): void
    {
        $marco = $this->actAsFieldOfficer();

        $this->queuedListingFor($marco);
        $this->queuedListingFor($marco, [
            'queue_status' => QueueStatus::visited()->value,
            'visited_at'   => \Carbon\Carbon::now(),
        ]);

        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertOk()
            ->assertExactJson(['queued' => 1]);
    }

    public function test_it_excludes_soft_deleted_listings(): void
    {
        $marco = $this->actAsFieldOfficer();

        $this->queuedListingFor($marco);
        $dead = $this->queuedListingFor($marco);
        $dead->delete();

        $this->getJson(route('api.v1.field.listings.counts'))
            ->assertOk()
            ->assertExactJson(['queued' => 1]);
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.counts');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
