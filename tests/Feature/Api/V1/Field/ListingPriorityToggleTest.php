<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingPriorityToggleTest extends TestCase
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

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'           => false,
            'verified_at'           => null,
            'queue_status'          => QueueStatus::assigned()->value,
            'assigned_to'           => $officer->id,
            'is_field_priority'     => false,
            'field_priority_order'  => null,
        ], $overrides));
    }

    // ===========================================================
    // Auth
    // ===========================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user    = $this->makeFieldUser();
        $token   = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;
        $listing = $this->assignedListingFor($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user    = User::factory()->create();
        $token   = $user->createToken('field-android', ['field'])->plainTextToken;
        $listing = Listing::factory()->create([
            'queue_status'         => QueueStatus::assigned()->value,
            'assigned_to'          => $user->id,
            'is_field_priority'    => false,
            'field_priority_order' => null,
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertForbidden();
    }

    // ===========================================================
    // Slice / cross-tenant (all 404 — anti-enumeration)
    // ===========================================================

    public function test_it_returns_404_for_nonexistent_uuid(): void
    {
        $this->actAsFieldOfficer();

        $this->patchJson(route('api.v1.field.listings.priority-toggle', (string) Str::uuid7()))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $user    = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($user);
        $listing->delete();

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_assigned_to_another_officer(): void
    {
        $this->actAsFieldOfficer();
        $other   = $this->makeFieldUser();
        $listing = $this->assignedListingFor($other);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_queue_status_is_not_assigned(): void
    {
        $user    = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($user, [
            'queue_status' => QueueStatus::visited()->value,
            'visited_at'   => now(),
        ]);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertNotFound();
    }

    // ===========================================================
    // State flip
    // ===========================================================

    public function test_it_toggles_priority_on_when_currently_off(): void
    {
        $user    = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($user, [
            'is_field_priority'    => false,
            'field_priority_order' => null,
        ]);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $listing->refresh();
        $this->assertTrue($listing->is_field_priority);
        $this->assertNull($listing->field_priority_order);
    }

    public function test_it_toggles_priority_off_and_clears_order_when_currently_on(): void
    {
        $user    = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($user, [
            'is_field_priority'    => true,
            'field_priority_order' => 3,
        ]);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertOk();

        $listing->refresh();
        $this->assertFalse($listing->is_field_priority);
        $this->assertNull($listing->field_priority_order);
    }

    public function test_it_does_not_touch_other_listings_priority_state(): void
    {
        $user     = $this->actAsFieldOfficer();
        $target   = $this->assignedListingFor($user, [
            'is_field_priority'    => false,
            'field_priority_order' => null,
        ]);
        $sibling  = $this->assignedListingFor($user, [
            'is_field_priority'    => true,
            'field_priority_order' => 7,
        ]);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $target->uuid))
            ->assertOk();

        $sibling->refresh();
        $this->assertTrue($sibling->is_field_priority);
        $this->assertSame(7, $sibling->field_priority_order);
    }

    // ===========================================================
    // Response shape
    // ===========================================================

    public function test_it_returns_exact_success_envelope(): void
    {
        $user    = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($user);

        $this->patchJson(route('api.v1.field.listings.priority-toggle', $listing->uuid))
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.priority-toggle');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertContains('api', $middleware);
    }
}
