<?php

namespace Tests\Feature\Admin;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingVisitedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function visitedListing(array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::visited()->value,
            'visited_at'   => Carbon::now(),
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $this->get(route('admin.visited-listings.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_manage_permission(): void
    {
        // Field role has `listings.field-work` but NOT `listings.manage` — must 403.
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.visited-listings.index'))
            ->assertForbidden();
    }

    public function test_it_returns_paginated_visited_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 12; $i++) {
            $this->visitedListing([
                'visited_at' => Carbon::parse('2026-04-30 09:00:00')->subSeconds($i),
                'type'       => 'apartment',
                'barangay'   => 'pueblo_de_oro',
            ]);
        }

        // Noise — must NOT appear
        $this->visitedListing(['is_verified' => true, 'verified_at' => Carbon::now()]);
        $this->visitedListing(['queue_status' => QueueStatus::assigned()->value]);

        $response = $this->get(route('admin.visited-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('visited');

        $this->assertSame(1,  $payload['meta']['current_page']);
        $this->assertSame(2,  $payload['meta']['last_page']);
        $this->assertSame(12, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);

        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label',
             'price_monthly', 'directions', 'contact_phone',
             'assigned_to_name', 'visited_at', 'updated_at'],
            array_keys($first),
        );

        $page2 = $this->get(route('admin.visited-listings.index', ['page' => 2]))->viewData('visited');
        $this->assertCount(2, $page2['data']);
        $this->assertSame(2, $page2['meta']['current_page']);
    }

    public function test_it_sorts_by_visited_at_asc_by_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $oldest = $this->visitedListing(['visited_at' => Carbon::parse('2026-04-28 09:00:00')]);
        $middle = $this->visitedListing(['visited_at' => Carbon::parse('2026-04-29 09:00:00')]);
        $newest = $this->visitedListing(['visited_at' => Carbon::parse('2026-04-30 09:00:00')]);

        $payload = $this->get(route('admin.visited-listings.index'))->viewData('visited');
        $ids = array_map(fn ($r) => $r['id'], $payload['data']);

        $this->assertSame([$oldest->id, $middle->id, $newest->id], $ids);
    }

    public function test_it_excludes_listings_with_queue_status_other_than_visited(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $visited    = $this->visitedListing();
        $assigned   = $this->visitedListing(['queue_status' => QueueStatus::assigned()->value]);
        $unassigned = $this->visitedListing(['queue_status' => QueueStatus::unassigned()->value]);
        $dead       = $this->visitedListing(['queue_status' => QueueStatus::dead()->value]);

        $payload = $this->get(route('admin.visited-listings.index'))->viewData('visited');
        $ids = array_map(fn ($r) => $r['id'], $payload['data']);

        $this->assertContains($visited->id, $ids);
        $this->assertNotContains($assigned->id,   $ids);
        $this->assertNotContains($unassigned->id, $ids);
        $this->assertNotContains($dead->id,       $ids);
    }

    public function test_it_excludes_already_verified_listings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $awaiting = $this->visitedListing();
        $verified = $this->visitedListing([
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-30 16:00:00'),
        ]);

        $payload = $this->get(route('admin.visited-listings.index'))->viewData('visited');
        $ids = array_map(fn ($r) => $r['id'], $payload['data']);

        $this->assertContains($awaiting->id, $ids);
        $this->assertNotContains($verified->id, $ids);
    }

    public function test_it_excludes_already_verified_listings_on_scout_search_path(): void
    {
        // Scout path (q != '') has its own where-chain. Both `queue_status='visited'`
        // AND `is_verified=false` must apply on Scout. Title-pinned all 3 fixtures
        // so the LIKE %pueblo% matches each — slice filter is the only thing that
        // should determine visibility. Barangay pinned to non-pueblo to avoid the
        // factory-default false-positive caught earlier.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $awaiting = $this->visitedListing(['title' => 'Pueblo plaza awaiting', 'barangay' => 'lapasan']);
        $verified = $this->visitedListing([
            'title'       => 'Pueblo plaza verified',
            'barangay'    => 'lapasan',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        $assigned = $this->visitedListing([
            'title'        => 'Pueblo plaza assigned',
            'barangay'     => 'lapasan',
            'queue_status' => QueueStatus::assigned()->value,
        ]);

        $payload = $this->get(route('admin.visited-listings.index', ['q' => 'pueblo']))->viewData('visited');
        $ids = array_map(fn ($r) => $r['id'], $payload['data']);

        $this->assertContains($awaiting->id, $ids);
        $this->assertNotContains($verified->id, $ids);
        $this->assertNotContains($assigned->id, $ids);
    }

    public function test_it_includes_assigned_to_name_when_listing_was_assigned(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $field = User::factory()->create(['name' => 'Marco Reyes']);

        $assignedRow = $this->visitedListing(['assigned_to' => $field->id]);
        $unassignedRow = $this->visitedListing(['assigned_to' => null]);

        $payload = $this->get(route('admin.visited-listings.index'))->viewData('visited');
        $rows = collect($payload['data']);

        $r1 = $rows->firstWhere('id', $assignedRow->id);
        $r2 = $rows->firstWhere('id', $unassignedRow->id);

        $this->assertSame('Marco Reyes', $r1['assigned_to_name']);
        $this->assertNull($r2['assigned_to_name']);
    }

    public function test_it_returns_resource_payload_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $field = User::factory()->create(['name' => 'Marco Reyes']);
        $listing = $this->visitedListing([
            'title'         => 'Apartment near Capitol',
            'type'          => ListingType::apartment()->value,
            'barangay'      => Barangay::lapasan()->value,
            'price_monthly' => 12500,
            'directions'    => 'Past the green gate.',
            'contact_phone' => '+639171234567',
            'assigned_to'   => $field->id,
            'visited_at'    => Carbon::parse('2026-04-30 14:00:00'),
        ]);

        $payload = $this->get(route('admin.visited-listings.index'))->viewData('visited');
        $row = collect($payload['data'])->firstWhere('id', $listing->id);

        $this->assertNotNull($row);
        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Apartment near Capitol', $row['name']);
        $this->assertSame(ListingType::apartment()->label, $row['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $row['barangay_label']);
        $this->assertSame(12500, $row['price_monthly']);
        $this->assertSame('Past the green gate.', $row['directions']);
        $this->assertSame('+639171234567', $row['contact_phone']);
        $this->assertSame('Marco Reyes', $row['assigned_to_name']);
        $this->assertNotNull($row['visited_at']);
        $this->assertNotNull($row['updated_at']);

        // Out-of-scope on this surface (every row IS visited; calls-team prequal
        // context belongs to the unverified queue).
        $this->assertArrayNotHasKey('prequal_status',       $row);
        $this->assertArrayNotHasKey('prequal_status_label', $row);
        $this->assertArrayNotHasKey('queue_status',         $row);
        $this->assertArrayNotHasKey('queue_status_label',   $row);
    }

    public function test_it_filters_by_search_query(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $matchingAwaiting = $this->visitedListing([
            'title'    => 'Pueblo de Oro condo',
            'barangay' => 'lapasan',
        ]);
        $otherAwaiting = $this->visitedListing([
            'title'    => 'Carmen apartment',
            'barangay' => 'lapasan',
        ]);
        $matchingVerified = $this->visitedListing([
            'title'       => 'Pueblo townhouse verified',
            'barangay'    => 'lapasan',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $payload = $this->get(route('admin.visited-listings.index', ['q' => 'pueblo']))->viewData('visited');
        $ids = array_map(fn ($r) => $r['id'], $payload['data']);

        $this->assertContains($matchingAwaiting->id, $ids);
        $this->assertNotContains($otherAwaiting->id, $ids);
        $this->assertNotContains($matchingVerified->id, $ids);
    }
}
