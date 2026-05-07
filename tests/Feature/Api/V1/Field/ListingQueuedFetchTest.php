<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingQueuedFetchTest extends TestCase
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
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to'  => $officer->id,
        ], $overrides));
    }

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $this->getJson(route('api.v1.field.listings.queued'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.field.listings.queued'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user  = $this->makeFieldUser();
        $token = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.queued'))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        // User without `field` role; manually issued `['field']`-ability token
        // (simulates: officer logged in with role, role revoked later, token still alive).
        // `abilities:field` passes; `can:listings.field-work` denies.
        $user  = User::factory()->create();
        $token = $user->createToken('field-android', ['field'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.queued'))
            ->assertForbidden();
    }

    public function test_it_returns_only_listings_assigned_to_current_officer(): void
    {
        $marco = $this->actAsFieldOfficer();
        $carlo = User::factory()->field()->create();

        $marcosA = $this->assignedListingFor($marco);
        $marcosB = $this->assignedListingFor($marco);
        $this->assignedListingFor($carlo);   // Carlo's — must NOT appear
        Listing::factory()->create([         // unassigned — must NOT appear
            'is_verified'  => false,
            'queue_status' => QueueStatus::unassigned()->value,
            'assigned_to'  => null,
        ]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid')->sort()->values()->all();
        $expected = collect([$marcosA->uuid, $marcosB->uuid])->sort()->values()->all();
        $this->assertSame($expected, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_other_than_assigned(): void
    {
        $marco = $this->actAsFieldOfficer();

        $assigned = $this->assignedListingFor($marco);
        $this->assignedListingFor($marco, ['queue_status' => QueueStatus::unassigned()->value]);
        $this->assignedListingFor($marco, ['queue_status' => QueueStatus::visited()->value]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($assigned->uuid, $data[0]['uuid']);
    }

    public function test_it_orders_listings_by_assigned_at_ascending(): void
    {
        $marco = $this->actAsFieldOfficer();

        $third  = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-30 10:00:00')]);
        $first  = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-28 09:00:00')]);
        $second = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-29 09:00:00')]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $data = $response->json('data');
        $this->assertSame($first->uuid,  $data[0]['uuid']);
        $this->assertSame($second->uuid, $data[1]['uuid']);
        $this->assertSame($third->uuid,  $data[2]['uuid']);
    }

    public function test_it_returns_resource_shape_with_expected_fields(): void
    {
        $marco = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco, [
            'title'                => 'Test Listing',
            'directions'           => 'Near Gaisano City',
            'contact_phone'        => '+639171234567',
            'contact_type'         => ContactType::owner()->value,
            'source_site'          => SourceSite::olx()->value,
            'source_url'           => 'https://olx.ph/test',
            'field_priority_order' => 1,
        ]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $row = $data[0];

        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Test Listing', $row['title']);
        $this->assertSame(ListingType::from($listing->type)->label, $row['type_label']);
        $this->assertSame(Barangay::from($listing->barangay)->label, $row['barangay_label']);
        $this->assertSame($listing->price_monthly, $row['price_monthly']);
        $this->assertSame($listing->beds, $row['beds']);
        $this->assertSame($listing->baths, $row['baths']);
        $this->assertSame($listing->sqm, $row['sqm']);
        $this->assertSame('Near Gaisano City', $row['directions']);
        $this->assertSame('+639171234567', $row['contact_phone']);
        $this->assertSame('Owner', $row['contact_type_label']);
        $this->assertSame('OLX', $row['source_site_label']);
        $this->assertSame('https://olx.ph/test', $row['source_url']);
        $this->assertArrayHasKey('display_image_url', $row);
        $this->assertArrayHasKey('assigned_at', $row);
        $this->assertSame(1, $row['field_priority_order']);
    }

    public function test_it_returns_assigned_at_from_the_real_column_not_updated_at_proxy(): void
    {
        $marco = $this->actAsFieldOfficer();

        $assignedAt = Carbon::parse('2026-04-20 10:00:00');
        $this->assignedListingFor($marco, ['assigned_at' => $assignedAt]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $this->assertSame($assignedAt->toIso8601String(), $response->json('data.0.assigned_at'));
    }

    public function test_it_returns_null_assigned_at_when_listing_has_no_assignment_timestamp(): void
    {
        $marco = $this->actAsFieldOfficer();
        $this->assignedListingFor($marco, ['assigned_at' => null]);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();

        $this->assertNull($response->json('data.0.assigned_at'));
    }

    public function test_it_filters_by_search_query(): void
    {
        $marco = $this->actAsFieldOfficer();

        $safeBarangay = ['barangay' => Barangay::lapasan()->value];

        $gaisano = $this->assignedListingFor($marco, $safeBarangay + ['title' => 'Apartment near Gaisano']);
        $carmen  = $this->assignedListingFor($marco, $safeBarangay + ['title' => 'Studio in Carmen']);
        $this->assignedListingFor($marco, $safeBarangay + ['title' => 'House in Kauswagan']);

        $response = $this->getJson(route('api.v1.field.listings.queued', ['q' => 'gaisano']))->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($gaisano->uuid, $data[0]['uuid']);

        $response = $this->getJson(route('api.v1.field.listings.queued', ['q' => 'carmen']))->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($carmen->uuid, $data[0]['uuid']);

        $response = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_paginates_at_10_per_page_with_load_more_meta(): void
    {
        $marco = $this->actAsFieldOfficer();

        // 11 assigned listings — page 1 has 10, page 2 has 1.
        for ($i = 0; $i < 11; $i++) {
            $this->assignedListingFor($marco, [
                'assigned_at' => Carbon::parse('2026-04-20 10:00:00')->addMinutes($i),
            ]);
        }

        $page1 = $this->getJson(route('api.v1.field.listings.queued'))->assertOk();
        $this->assertCount(10, $page1->json('data'));
        $this->assertSame(1,  $page1->json('meta.current_page'));
        $this->assertSame(2,  $page1->json('meta.last_page'));
        $this->assertSame(10, $page1->json('meta.per_page'));
        $this->assertSame(11, $page1->json('meta.total'));

        $page2 = $this->getJson(route('api.v1.field.listings.queued', ['page' => 2]))->assertOk();
        $this->assertCount(1, $page2->json('data'));
        $this->assertSame(2, $page2->json('meta.current_page'));
        $this->assertSame(2, $page2->json('meta.last_page'));
    }

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities_and_field_work_permission(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.queued');

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
