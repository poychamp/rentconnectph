<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingDeactivatedTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.deactivated-listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_paginated_deactivated_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 7 deactivated listings (is_verified=true + soft-deleted), each with one
        // `deactivated` lifecycle event. deleted_at spaced 1 day apart so the
        // default `deleted_at DESC` sort is unambiguous.
        $deactivatedIds = [];
        for ($i = 0; $i < 7; $i++) {
            $listing = Listing::factory()->create([
                'is_verified' => true,
                'verified_at' => Carbon::now()->subDays($i + 7),
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ]);
            $listing->delete();
            $listing->update(['deleted_at' => Carbon::now()->subDays($i)]);

            ListingLifecycleEvent::factory()->deactivated()->create([
                'listing_id' => $listing->id,
                'actor_id'   => $admin->id,
                'reason'     => 'rented_out',  // pinned so we can assert the label exactly
                'created_at' => Carbon::now()->subDays($i),
            ]);

            $deactivatedIds[] = $listing->id;
        }

        // Noise that must NOT appear in the result —
        // exercises the four-slice filter exhaustively:

        // 5 verified-live (is_verified=true, deleted_at=null)
        for ($i = 0; $i < 5; $i++) {
            Listing::factory()->create([
                'is_verified' => true,
                'verified_at' => Carbon::now(),
                'type'        => 'apartment',
                'barangay'    => 'carmen',
            ]);
        }

        // 3 unverified-live (is_verified=false, deleted_at=null)
        for ($i = 0; $i < 3; $i++) {
            Listing::factory()->create([
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'apartment',
                'barangay'    => 'lapasan',
            ]);
        }

        // 3 rejected (is_verified=false + soft-deleted) — future Rejected page's
        // slice. CRITICAL: these MUST be excluded from /admin/deactivated-listings.
        // The controller's filter is `is_verified=true AND deleted_at NOT NULL` —
        // it doesn't care whether a lifecycle event exists, so no event seeded.
        for ($i = 0; $i < 3; $i++) {
            Listing::factory()->create([
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'studio',
                'barangay'    => 'kauswagan',
            ])->delete();
        }

        // ---- Hit the route ----
        $response = $this->get(route('admin.deactivated-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('deactivated');

        // Pagination meta — Laravel paginator standard
        $this->assertSame(1, $payload['meta']['current_page']);
        $this->assertSame(1, $payload['meta']['last_page']);    // 7 < perPage 10 → single page
        $this->assertSame(7, $payload['meta']['total']);        // ONLY the 7 deactivated; verified/unverified/rejected excluded
        $this->assertSame(10, $payload['meta']['per_page']);

        // Data — page 1 = 7 rows
        $this->assertCount(7, $payload['data']);

        // Resource shape — keys list. Includes the new audit-log fields.
        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label', 'price', 'created_at', 'deleted_at', 'deactivation_reason', 'deactivated_by'],
            array_keys($first)
        );

        // Human-readable enum labels
        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        // Audit-log fields — pin the human-readable label, NOT the raw stored value.
        // If this fails with `'rented_out'` instead of `'Rented Out'`, the resource
        // forgot to map through DeactivationReason::from(...)->label.
        foreach ($payload['data'] as $row) {
            $this->assertSame('Rented Out', $row['deactivation_reason'],
                'deactivation_reason must be the human-readable label, not the raw value');
            $this->assertSame($admin->name, $row['deactivated_by'],
                'deactivated_by must be the actor user\'s name from the latest event');
        }

        // Both timestamps must include time component (HH:MM:SS), not just date.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['created_at'],
            'created_at must be an ISO 8601 datetime with time component'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['deleted_at'],
            'deleted_at must be an ISO 8601 datetime with time component'
        );

        // Sort order — most-recently-deactivated first (deleted_at DESC).
        // Newest deactivation is at index 0; oldest at index 6.
        $deletedAts = array_map(fn ($r) => $r['deleted_at'], $payload['data']);
        $expected = $deletedAts;
        rsort($expected);  // ISO 8601 strings sort lexicographically the same as chronologically
        $this->assertSame($expected, $deletedAts, 'Rows must be ordered by deleted_at DESC');

        // CRITICAL: scope filter — every returned row's id must be from the
        // deactivated set. None of the verified-live, unverified-live, or
        // rejected ids should leak into the result.
        $returnedIds = array_map(fn ($r) => $r['id'], $payload['data']);
        foreach ($returnedIds as $id) {
            $this->assertContains($id, $deactivatedIds,
                "Row id {$id} should be from the deactivated slice; verified-live, unverified-live, or rejected leaked into the result");
        }
    }

    public function test_it_filters_by_search_query_excluding_rejected_and_verified_lookalikes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Three DEACTIVATED listings — these are the rows that may match.
        $cozyDeactivated = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(10),
            'title'       => 'Cozy 2-BR Apartment',
            'type'        => 'apartment',
            'barangay'    => 'carmen',
        ]);
        $cozyDeactivated->delete();
        ListingLifecycleEvent::factory()->deactivated()->create([
            'listing_id' => $cozyDeactivated->id,
            'actor_id'   => $admin->id,
            'reason'     => 'rented_out',
            'created_at' => Carbon::now(),
        ]);

        $pueblo = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(10),
            'title'       => 'Modern Condo',
            'type'        => 'condo',
            'barangay'    => 'pueblo_de_oro',
        ]);
        $pueblo->delete();
        ListingLifecycleEvent::factory()->deactivated()->create([
            'listing_id' => $pueblo->id,
            'actor_id'   => $admin->id,
            'reason'     => 'unavailable',
            'created_at' => Carbon::now(),
        ]);

        $spacious = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(10),
            'title'       => 'Spacious House',
            'type'        => 'house',
            'barangay'    => 'kauswagan',
        ]);
        $spacious->delete();
        ListingLifecycleEvent::factory()->deactivated()->create([
            'listing_id' => $spacious->id,
            'actor_id'   => $admin->id,
            'reason'     => 'other',
            'created_at' => Carbon::now(),
        ]);

        // Critical noise #1: a REJECTED listing matching the same search keyword.
        // This is the lookalike that would silently slip through if the controller's
        // Scout branch dropped the `where('is_verified', true)` clause.
        $cozyRejected = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Cozy Spam Listing',
            'type'        => 'apartment',
            'barangay'    => 'lapasan',
        ]);
        $cozyRejected->delete();

        // Critical noise #2: a VERIFIED-LIVE listing sharing the keyword.
        // The controller's `onlyTrashed()` MUST exclude it.
        Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'title'       => 'Cozy Verified Studio',
            'type'        => 'studio',
            'barangay'    => 'lapasan',
        ]);

        // ---- Assertions ----

        // ?q=cozy must match ONLY the deactivated cozy.
        // Rejected `Cozy Spam Listing` and verified `Cozy Verified Studio` are excluded.
        // If this fails, either:
        //   (a) `where('is_verified', true)` got dropped from the Scout branch, or
        //   (b) `onlyTrashed()` got dropped, or
        //   (c) `Listing::toSearchableArray()` isn't indexing `is_verified` as the
        //       string '1' (Scout's quoted facet match would silently miss).
        $response = $this->get(route('admin.deactivated-listings.index', ['q' => 'cozy']));
        $response->assertOk();
        $payload = $response->viewData('deactivated');
        $this->assertCount(1, $payload['data'], 'Only the deactivated Cozy must match — rejected + verified Cozy must be excluded');
        $this->assertSame($cozyDeactivated->id, $payload['data'][0]['id']);

        // ?q=pueblo — barangay slug substring match (Scout DB driver does single-string LIKE)
        $response = $this->get(route('admin.deactivated-listings.index', ['q' => 'pueblo']));
        $payload  = $response->viewData('deactivated');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($pueblo->id, $payload['data'][0]['id']);

        // ?q=spam — would match the rejected `Cozy Spam Listing` if the slice
        // filter weren't applied. Must return 0 (rejected ≠ deactivated).
        $response = $this->get(route('admin.deactivated-listings.index', ['q' => 'spam']));
        $this->assertCount(0, $response->viewData('deactivated')['data'],
            'Rejected listings must NOT appear on the deactivated page — even when the search keyword matches');

        // Empty / missing q → all 3 deactivated rows (rejected + verified excluded)
        $response = $this->get(route('admin.deactivated-listings.index'));
        $this->assertSame(3, $response->viewData('deactivated')['meta']['total']);

        // No match → empty
        $response = $this->get(route('admin.deactivated-listings.index', ['q' => 'xyznomatch']));
        $this->assertCount(0, $response->viewData('deactivated')['data']);
    }
}
