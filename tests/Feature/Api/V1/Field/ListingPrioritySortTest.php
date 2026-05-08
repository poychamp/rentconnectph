<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingPrioritySortTest extends TestCase
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

    private function priorityListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'          => false,
            'verified_at'          => null,
            'queue_status'         => QueueStatus::assigned()->value,
            'assigned_to'          => $officer->id,
            'assigned_at'          => Carbon::parse('2026-04-29 10:00:00'),
            'is_field_priority'    => true,
            'field_priority_order' => null,
        ], $overrides));
    }

    // ===========================================================
    // Auth
    // ===========================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->patchJson(route('api.v1.field.listings.priority-sort'), [
                'order' => ['00000000-0000-0000-0000-000000000000'],
            ])->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.priority-sort'), [
                'order' => ['00000000-0000-0000-0000-000000000000'],
            ])->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.priority-sort'), [
                'order' => ['00000000-0000-0000-0000-000000000000'],
            ])->assertForbidden();
    }

    // ===========================================================
    // Validation
    // ===========================================================

    public function test_it_validates_order_payload(): void
    {
        $this->actAsFieldOfficer();

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [])
            ->assertJsonValidationErrors(['order']);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), ['order' => 'not-an-array'])
            ->assertJsonValidationErrors(['order']);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), ['order' => []])
            ->assertJsonValidationErrors(['order']);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), ['order' => ['not-a-uuid']])
            ->assertJsonValidationErrors(['order.0']);
    }

    // ===========================================================
    // Pool / cross-tenant
    // ===========================================================

    public function test_it_returns_422_when_one_or_more_uuids_are_not_in_the_officers_priority_pool(): void
    {
        $marco        = $this->actAsFieldOfficer();
        $marcoListing = $this->priorityListingFor($marco, ['field_priority_order' => 1]);

        $carlo        = $this->makeFieldUser();
        $carloListing = $this->priorityListingFor($carlo, ['field_priority_order' => 1]);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => [$marcoListing->uuid, $carloListing->uuid],
        ])->assertJsonValidationErrors(['order']);
    }

    // ===========================================================
    // Persistence
    // ===========================================================

    public function test_it_persists_the_new_order(): void
    {
        $marco = $this->actAsFieldOfficer();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $c = $this->priorityListingFor($marco, ['field_priority_order' => 3]);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => [$c->uuid, $a->uuid, $b->uuid],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, $c->fresh()->field_priority_order);
        $this->assertSame(2, $a->fresh()->field_priority_order);
        $this->assertSame(3, $b->fresh()->field_priority_order);
    }

    public function test_it_does_not_change_queue_status(): void
    {
        $marco = $this->actAsFieldOfficer();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => [$b->uuid, $a->uuid],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(QueueStatus::assigned()->value, $a->fresh()->queue_status);
        $this->assertSame(QueueStatus::assigned()->value, $b->fresh()->queue_status);
    }

    public function test_it_does_not_change_is_field_priority(): void
    {
        $marco = $this->actAsFieldOfficer();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => [$b->uuid, $a->uuid],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue((bool) $a->fresh()->is_field_priority);
        $this->assertTrue((bool) $b->fresh()->is_field_priority);
    }

    // ===========================================================
    // Response shape
    // ===========================================================

    public function test_it_returns_exact_success_envelope(): void
    {
        $marco = $this->actAsFieldOfficer();
        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $this->patchJson(route('api.v1.field.listings.priority-sort'), [
            'order' => [$b->uuid, $a->uuid],
        ])->assertOk()->assertExactJson(['success' => true]);
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.priority-sort');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertContains('api', $middleware);
    }
}
