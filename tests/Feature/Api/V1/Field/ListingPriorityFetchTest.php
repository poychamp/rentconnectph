<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingPriorityFetchTest extends TestCase
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
            'is_field_priority'    => true,
            'field_priority_order' => 1,
        ], $overrides));
    }

    // ===========================================================
    // Auth
    // ===========================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->getJson(route('api.v1.field.listings.priority'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.field.listings.priority'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.priority'))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.priority'))
            ->assertForbidden();
    }

    // ===========================================================
    // Slice / scope
    // ===========================================================

    public function test_it_returns_empty_data_when_officer_has_no_priority_listings(): void
    {
        $this->actAsFieldOfficer();

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_it_excludes_priority_listings_assigned_to_other_officers(): void
    {
        $marco = $this->actAsFieldOfficer();
        $other = $this->makeFieldUser();

        $mine    = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $theirs  = $this->priorityListingFor($other, ['field_priority_order' => 1]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($mine->uuid, $uuids);
        $this->assertNotContains($theirs->uuid, $uuids);
    }

    public function test_it_excludes_own_listings_with_is_field_priority_false(): void
    {
        $marco = $this->actAsFieldOfficer();

        $priority = $this->priorityListingFor($marco);
        $regular  = $this->priorityListingFor($marco, [
            'is_field_priority'    => false,
            'field_priority_order' => null,
        ]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($priority->uuid, $uuids);
        $this->assertNotContains($regular->uuid, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_not_assigned(): void
    {
        $marco = $this->actAsFieldOfficer();

        $assigned = $this->priorityListingFor($marco);
        $visited  = $this->priorityListingFor($marco, [
            'queue_status' => QueueStatus::visited()->value,
            'visited_at'   => Carbon::now(),
        ]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($assigned->uuid, $uuids);
        $this->assertNotContains($visited->uuid, $uuids);
    }

    public function test_it_excludes_soft_deleted_listings(): void
    {
        $marco = $this->actAsFieldOfficer();

        $alive = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $dead  = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $dead->delete();

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertContains($alive->uuid, $uuids);
        $this->assertNotContains($dead->uuid, $uuids);
    }

    // ===========================================================
    // Order + self-heal
    // ===========================================================

    public function test_it_orders_listings_by_field_priority_order_ascending(): void
    {
        $marco = $this->actAsFieldOfficer();

        $third  = $this->priorityListingFor($marco, ['field_priority_order' => 3]);
        $first  = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $second = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();
        $data = $response->json('data');

        $this->assertSame($first->uuid,  $data[0]['uuid']);
        $this->assertSame($second->uuid, $data[1]['uuid']);
        $this->assertSame($third->uuid,  $data[2]['uuid']);
    }

    public function test_it_self_heals_null_field_priority_order_by_appending_past_max(): void
    {
        $marco = $this->actAsFieldOfficer();

        $regular = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $broken  = $this->priorityListingFor($marco, ['field_priority_order' => null]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();

        $this->assertDatabaseHas('listings', [
            'id'                   => $regular->id,
            'field_priority_order' => 1,
        ]);
        $this->assertDatabaseHas('listings', [
            'id'                   => $broken->id,
            'field_priority_order' => 2,
        ]);

        $data = $response->json('data');
        $this->assertSame($regular->uuid, $data[0]['uuid']);
        $this->assertSame($broken->uuid,  $data[1]['uuid']);
        $this->assertSame(2,              $data[1]['field_priority_order']);
    }

    public function test_it_self_heals_duplicate_field_priority_order_by_appending_past_max(): void
    {
        $marco = $this->actAsFieldOfficer();

        $first  = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $second = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $dup    = $this->priorityListingFor($marco, ['field_priority_order' => 2]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();

        $this->assertDatabaseHas('listings', [
            'id'                   => $first->id,
            'field_priority_order' => 1,
        ]);
        $this->assertDatabaseHas('listings', [
            'id'                   => $second->id,
            'field_priority_order' => 2,
        ]);
        $this->assertDatabaseHas('listings', [
            'id'                   => $dup->id,
            'field_priority_order' => 3,
        ]);
    }

    public function test_it_is_idempotent_when_orders_are_already_clean(): void
    {
        $marco = $this->actAsFieldOfficer();

        $a = $this->priorityListingFor($marco, ['field_priority_order' => 1]);
        $b = $this->priorityListingFor($marco, ['field_priority_order' => 2]);
        $c = $this->priorityListingFor($marco, ['field_priority_order' => 3]);

        $this->getJson(route('api.v1.field.listings.priority'))->assertOk();

        $this->assertDatabaseHas('listings', ['id' => $a->id, 'field_priority_order' => 1]);
        $this->assertDatabaseHas('listings', ['id' => $b->id, 'field_priority_order' => 2]);
        $this->assertDatabaseHas('listings', ['id' => $c->id, 'field_priority_order' => 3]);
    }

    // ===========================================================
    // Response shape
    // ===========================================================

    public function test_it_returns_resource_shape_with_expected_fields(): void
    {
        $marco = $this->actAsFieldOfficer();
        $listing = $this->priorityListingFor($marco, [
            'title'                => 'Priority House',
            'type'                 => ListingType::house()->value,
            'barangay'             => Barangay::carmen()->value,
            'price_monthly'        => 25000,
            'directions'           => 'Near SM CDO',
            'field_priority_order' => 1,
            'assigned_at'          => Carbon::parse('2026-05-01 09:00:00'),
        ]);

        $response = $this->getJson(route('api.v1.field.listings.priority'))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $row = $data[0];

        $this->assertSame($listing->uuid,                                 $row['uuid']);
        $this->assertSame('Priority House',                               $row['title']);
        $this->assertSame(ListingType::from($listing->type)->label,       $row['type_label']);
        $this->assertSame(Barangay::from($listing->barangay)->label,      $row['barangay_label']);
        $this->assertSame(25000,                                          $row['price_monthly']);
        $this->assertSame('Near SM CDO',                                  $row['directions']);
        $this->assertNull($row['display_image_url']);
        $this->assertSame(1,                                              $row['field_priority_order']);
        $this->assertSame('2026-05-01T09:00:00+08:00',                    $row['assigned_at']);

        $this->assertSame(
            ['uuid', 'title', 'type_label', 'barangay_label', 'price_monthly',
             'directions', 'display_image_url', 'field_priority_order', 'assigned_at'],
            array_keys($row),
        );
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.priority');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertContains('api', $middleware);
    }
}
