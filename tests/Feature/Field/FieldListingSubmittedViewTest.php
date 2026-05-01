<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingSubmittedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function submittedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::visited()->value,
            'assigned_to'  => $officer->id,
            'assigned_at'  => Carbon::parse('2026-04-29 10:00:00'),
            'visited_at'   => Carbon::parse('2026-04-30 14:00:00'),
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $this->get(route('field.submitted-listings.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $this->get(route('field.submitted-listings.index'))
            ->assertForbidden();
    }

    public function test_it_returns_only_visited_listings_assigned_to_the_current_officer(): void
    {
        $marco = $this->asMarco();
        $marcosA = $this->submittedListingFor($marco);
        $marcosB = $this->submittedListingFor($marco);

        $carlo = User::factory()->field()->create();
        $carlosVisited = $this->submittedListingFor($carlo);

        $response = $this->get(route('field.submitted-listings.index'));

        $response->assertOk();
        $payload = $response->viewData('submitted')['data'];

        $uuids = collect($payload)->pluck('uuid')->all();
        $this->assertContains($marcosA->uuid, $uuids);
        $this->assertContains($marcosB->uuid, $uuids);
        $this->assertNotContains($carlosVisited->uuid, $uuids);
    }

    public function test_it_excludes_listings_with_queue_status_other_than_visited(): void
    {
        $marco = $this->asMarco();
        $visited    = $this->submittedListingFor($marco);
        $assigned   = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::assigned()->value]);
        $unassigned = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::unassigned()->value]);
        $dead       = $this->submittedListingFor($marco, ['queue_status' => QueueStatus::dead()->value]);

        $response = $this->get(route('field.submitted-listings.index'));
        $payload = $response->viewData('submitted')['data'];

        $uuids = collect($payload)->pluck('uuid')->all();
        $this->assertContains($visited->uuid, $uuids);
        $this->assertNotContains($assigned->uuid, $uuids);
        $this->assertNotContains($unassigned->uuid, $uuids);
        $this->assertNotContains($dead->uuid, $uuids);
    }

    public function test_it_excludes_listings_that_are_already_verified(): void
    {
        $marco = $this->asMarco();
        $awaiting = $this->submittedListingFor($marco);
        $verified = $this->submittedListingFor($marco, [
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-30 16:00:00'),
        ]);

        $response = $this->get(route('field.submitted-listings.index'));
        $uuids = collect($response->viewData('submitted')['data'])->pluck('uuid')->all();

        $this->assertContains($awaiting->uuid, $uuids);
        $this->assertNotContains($verified->uuid, $uuids);
    }

    public function test_it_orders_by_visited_at_desc_then_id_desc(): void
    {
        $marco = $this->asMarco();

        $oldest = $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-28 09:00:00')]);
        $middle = $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-29 09:00:00')]);
        $newest = $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-30 09:00:00')]);

        // Tie-break: same visited_at, id DESC wins.
        $tieEarlier = $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-28 18:00:00')]);
        $tieLater   = $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-28 18:00:00')]);

        $response = $this->get(route('field.submitted-listings.index'));
        $uuids = collect($response->viewData('submitted')['data'])->pluck('uuid')->all();

        $this->assertSame(
            [$newest->uuid, $middle->uuid, $tieLater->uuid, $tieEarlier->uuid, $oldest->uuid],
            $uuids,
        );
    }

    public function test_it_paginates_results_at_10_per_page(): void
    {
        $marco = $this->asMarco();
        for ($i = 0; $i < 12; $i++) {
            $this->submittedListingFor($marco, ['visited_at' => Carbon::parse('2026-04-30 09:00:00')->subSeconds($i)]);
        }

        $page1 = $this->get(route('field.submitted-listings.index'))->viewData('submitted');
        $this->assertCount(10, $page1['data']);
        $this->assertSame(2, $page1['meta']['last_page']);
        $this->assertSame(12, $page1['meta']['total']);
        $this->assertSame(10, $page1['meta']['per_page']);

        $page2 = $this->get(route('field.submitted-listings.index') . '?page=2')->viewData('submitted');
        $this->assertCount(2, $page2['data']);
        $this->assertSame(2, $page2['meta']['current_page']);
    }

    public function test_it_returns_resource_payload_with_expected_shape(): void
    {
        $marco = $this->asMarco();
        $listing = $this->submittedListingFor($marco, [
            'title'         => 'Apartment near Capitol',
            'type'          => ListingType::apartment()->value,
            'barangay'      => Barangay::lapasan()->value,
            'price_monthly' => 12500,
            'directions'    => 'Past the green gate.',
        ]);

        $response = $this->get(route('field.submitted-listings.index'));
        $row = collect($response->viewData('submitted')['data'])->firstWhere('uuid', $listing->uuid);

        $this->assertNotNull($row);
        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Apartment near Capitol', $row['title']);
        $this->assertSame(ListingType::apartment()->label, $row['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $row['barangay_label']);
        $this->assertSame(12500, $row['price_monthly']);
        $this->assertSame('Past the green gate.', $row['directions']);
        $this->assertArrayHasKey('display_image_url', $row);
        $this->assertArrayHasKey('visited_at', $row);
        $this->assertNotNull($row['visited_at']);

        // Out-of-scope fields stay out.
        $this->assertArrayNotHasKey('is_verified', $row);
        $this->assertArrayNotHasKey('field_priority_order', $row);
        $this->assertArrayNotHasKey('is_field_priority', $row);
        $this->assertArrayNotHasKey('contact_phone', $row);
        $this->assertArrayNotHasKey('contact_type_label', $row);
        $this->assertArrayNotHasKey('source_site_label', $row);
        $this->assertArrayNotHasKey('source_url', $row);
        $this->assertArrayNotHasKey('beds', $row);
        $this->assertArrayNotHasKey('baths', $row);
        $this->assertArrayNotHasKey('sqm', $row);
        $this->assertArrayNotHasKey('assigned_at', $row);
    }

    public function test_it_filters_results_via_scout_search(): void
    {
        $marco = $this->asMarco();

        // Pin barangay to a non-pueblo value on all rows so the search query
        // ('pueblo') only matches via the title we explicitly set — otherwise
        // factory's random barangay could be 'pueblo_de_oro' and false-positive.
        $matchingVisited = $this->submittedListingFor($marco, [
            'title'    => 'Pueblo de Oro condo',
            'barangay' => Barangay::lapasan()->value,
        ]);
        $otherVisited = $this->submittedListingFor($marco, [
            'title'    => 'Carmen apartment',
            'barangay' => Barangay::lapasan()->value,
        ]);
        $matchingAssigned = $this->submittedListingFor($marco, [
            'title'        => 'Pueblo townhouse',
            'barangay'     => Barangay::lapasan()->value,
            'queue_status' => QueueStatus::assigned()->value,
        ]);

        $response = $this->get(route('field.submitted-listings.index') . '?q=pueblo');
        $uuids = collect($response->viewData('submitted')['data'])->pluck('uuid')->all();

        $this->assertContains($matchingVisited->uuid, $uuids);
        $this->assertNotContains($otherVisited->uuid, $uuids);
        $this->assertNotContains($matchingAssigned->uuid, $uuids);
    }
}
