<?php

namespace Tests\Feature\Field;

use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified' => false,
            'verified_at' => null,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to' => $officer->id,
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('field.listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        // No-role user — authed admin guard but no `field` role grant.
        // Super-admin can't be used here (Gate::before bypasses the check).
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $response = $this->get(route('field.listings.index'));

        $response->assertForbidden();
    }

    public function test_it_returns_only_listings_assigned_to_current_officer(): void
    {
        $marco = $this->asMarco();
        $carlo = User::factory()->field()->create();

        $marcosA = $this->assignedListingFor($marco);
        $marcosB = $this->assignedListingFor($marco);
        $this->assignedListingFor($carlo);  // Carlo's — must NOT appear
        Listing::factory()->create([        // unassigned — must NOT appear
            'is_verified' => false,
            'queue_status' => QueueStatus::unassigned()->value,
            'assigned_to' => null,
        ]);

        $response = $this->get(route('field.listings.index'));

        $response->assertOk();
        $listings = $response->viewData('listings');
        $uuids = array_column($listings, 'uuid');
        sort($uuids);
        $expected = [$marcosA->uuid, $marcosB->uuid];
        sort($expected);
        $this->assertSame($expected, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_other_than_assigned(): void
    {
        $marco = $this->asMarco();

        $assigned = $this->assignedListingFor($marco);
        $this->assignedListingFor($marco, ['queue_status' => QueueStatus::unassigned()->value]);
        $this->assignedListingFor($marco, ['queue_status' => QueueStatus::visited()->value]);

        $response = $this->get(route('field.listings.index'));

        $listings = $response->viewData('listings');
        $this->assertCount(1, $listings);
        $this->assertSame($assigned->uuid, $listings[0]['uuid']);
    }

    public function test_it_orders_listings_by_assigned_at_ascending(): void
    {
        $marco = $this->asMarco();

        // Insertion order: third(newest), first(oldest), second(middle).
        $third  = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-30 10:00:00')]);
        $first  = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-28 09:00:00')]);
        $second = $this->assignedListingFor($marco, ['assigned_at' => Carbon::parse('2026-04-29 09:00:00')]);

        $response = $this->get(route('field.listings.index'));

        $listings = $response->viewData('listings');
        $this->assertSame($first->uuid,  $listings[0]['uuid']);
        $this->assertSame($second->uuid, $listings[1]['uuid']);
        $this->assertSame($third->uuid,  $listings[2]['uuid']);
    }

    public function test_it_returns_resource_shape_with_expected_fields(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'title'          => 'Test Listing',
            'directions'     => 'Near Gaisano City',
            'contact_phone'  => '+639171234567',
            'contact_type'   => \App\Enums\ContactType::owner()->value,
            'source_site'    => \App\Enums\SourceSite::olx()->value,
            'source_url'     => 'https://olx.ph/test',
            'field_priority_order' => 1,
        ]);

        $response = $this->get(route('field.listings.index'));

        $listings = $response->viewData('listings');
        $this->assertCount(1, $listings);
        $row = $listings[0];

        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Test Listing', $row['title']);
        $this->assertSame(\App\Enums\ListingType::from($listing->type)->label, $row['type_label']);
        $this->assertSame(\App\Enums\Barangay::from($listing->barangay)->label, $row['barangay_label']);
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
        $marco = $this->asMarco();

        // Set assigned_at to a date in the past; updated_at auto-populates to
        // create-time (now). The two differ — resource MUST surface assigned_at,
        // not the updated_at proxy.
        $assignedAt = Carbon::parse('2026-04-20 10:00:00');
        $this->assignedListingFor($marco, ['assigned_at' => $assignedAt]);

        $response = $this->get(route('field.listings.index'));

        $listings = $response->viewData('listings');
        $this->assertSame($assignedAt->toIso8601String(), $listings[0]['assigned_at']);
    }

    public function test_it_returns_null_assigned_at_when_listing_has_no_assignment_timestamp(): void
    {
        $marco = $this->asMarco();

        // assigned_at = null. updated_at is non-null (factory create-time).
        // If resource is still on the updated_at proxy, this surfaces as an ISO
        // string instead of null.
        $this->assignedListingFor($marco, ['assigned_at' => null]);

        $response = $this->get(route('field.listings.index'));

        $listings = $response->viewData('listings');
        $this->assertNull($listings[0]['assigned_at']);
    }

    public function test_it_filters_by_search_query(): void
    {
        $marco = $this->asMarco();

        // Pin barangay to a value that doesn't collide with any test search term.
        // Factory's random barangay can land on `carmen` or `kauswagan` (both real
        // enum values), leaking matches via the barangay searchable field.
        $safeBarangay = ['barangay' => \App\Enums\Barangay::lapasan()->value];

        $gaisano   = $this->assignedListingFor($marco, $safeBarangay + ['title' => 'Apartment near Gaisano']);
        $carmen    = $this->assignedListingFor($marco, $safeBarangay + ['title' => 'Studio in Carmen']);
        $kauswagan = $this->assignedListingFor($marco, $safeBarangay + ['title' => 'House in Kauswagan']);

        // q=gaisano → only the Gaisano listing
        $response = $this->get(route('field.listings.index', ['q' => 'gaisano']));
        $listings = $response->viewData('listings');
        $this->assertCount(1, $listings);
        $this->assertSame($gaisano->uuid, $listings[0]['uuid']);

        // q=carmen → only the Carmen listing
        $response = $this->get(route('field.listings.index', ['q' => 'carmen']));
        $listings = $response->viewData('listings');
        $this->assertCount(1, $listings);
        $this->assertSame($carmen->uuid, $listings[0]['uuid']);

        // empty q → all 3
        $response = $this->get(route('field.listings.index'));
        $listings = $response->viewData('listings');
        $this->assertCount(3, $listings);
    }

    public function test_it_passes_is_searching_flag_based_on_query_presence(): void
    {
        $this->asMarco();

        // No q → false
        $this->assertFalse(
            $this->get(route('field.listings.index'))->viewData('isSearching')
        );

        // q=foo → true
        $this->assertTrue(
            $this->get(route('field.listings.index', ['q' => 'foo']))->viewData('isSearching')
        );

        // q=  (whitespace only) → false (controller trims)
        $this->assertFalse(
            $this->get(route('field.listings.index', ['q' => '   ']))->viewData('isSearching')
        );
    }
}
