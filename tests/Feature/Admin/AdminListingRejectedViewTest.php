<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingRejectedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.rejected-listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_paginated_rejected_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 7 rejected listings (is_verified=false + soft-deleted), each with one
        // `rejected` lifecycle event. deleted_at spaced 1 day apart so the
        // default `deleted_at DESC` sort is unambiguous.
        $rejectedIds = [];
        for ($i = 0; $i < 7; $i++) {
            $listing = Listing::factory()->create([
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ]);
            $listing->delete();
            $listing->update(['deleted_at' => Carbon::now()->subDays($i)]);

            ListingLifecycleEvent::factory()->create([
                'listing_id' => $listing->id,
                'actor_id'   => $admin->id,
                'event_type' => 'rejected',
                'reason'     => null,
                'notes'      => null,
                'created_at' => Carbon::now()->subDays($i),
            ]);

            $rejectedIds[] = $listing->id;
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

        // 3 unverified-live (is_verified=false, deleted_at=null) — same
        // is_verified flag as rejected; only the trashed status differs.
        for ($i = 0; $i < 3; $i++) {
            Listing::factory()->create([
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'apartment',
                'barangay'    => 'lapasan',
            ]);
        }

        // 3 deactivated (is_verified=true + soft-deleted) — the OTHER trash slice.
        // CRITICAL: these MUST be excluded from /admin/rejected-listings. The
        // controller's filter is `is_verified=false AND deleted_at NOT NULL`.
        for ($i = 0; $i < 3; $i++) {
            $deactivated = Listing::factory()->create([
                'is_verified' => true,
                'verified_at' => Carbon::now(),
                'type'        => 'studio',
                'barangay'    => 'kauswagan',
            ]);
            $deactivated->delete();
        }

        // ---- Hit the route ----
        $response = $this->get(route('admin.rejected-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('rejected');

        // Pagination meta — Laravel paginator standard
        $this->assertSame(1, $payload['meta']['current_page']);
        $this->assertSame(1, $payload['meta']['last_page']);
        $this->assertSame(7, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);

        // Data — page 1 = 7 rows
        $this->assertCount(7, $payload['data']);

        // Resource shape — keys list. NO rejection_reason field in v1.
        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label', 'price', 'created_at', 'rejected_at', 'rejected_by'],
            array_keys($first)
        );

        // Human-readable enum labels
        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        // Audit-log field — actor name on every row.
        foreach ($payload['data'] as $row) {
            $this->assertSame($admin->name, $row['rejected_by'],
                'rejected_by must be the actor user\'s name from the latest event');
        }

        // Both timestamps must include time component (HH:MM:SS), not just date.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['created_at'],
            'created_at must be an ISO 8601 datetime with time component'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['rejected_at'],
            'rejected_at must be an ISO 8601 datetime with time component'
        );

        // Sort order — most-recently-rejected first (deleted_at DESC).
        $rejectedAts = array_map(fn ($r) => $r['rejected_at'], $payload['data']);
        $expected = $rejectedAts;
        rsort($expected);
        $this->assertSame($expected, $rejectedAts, 'Rows must be ordered by deleted_at DESC');

        // CRITICAL: scope filter — every returned row's id must be from the
        // rejected set. None of the verified-live, unverified-live, or
        // deactivated ids should leak into the result.
        $returnedIds = array_map(fn ($r) => $r['id'], $payload['data']);
        foreach ($returnedIds as $id) {
            $this->assertContains($id, $rejectedIds,
                "Row id {$id} should be from the rejected slice; verified-live, unverified-live, or deactivated leaked into the result");
        }
    }

    public function test_it_filters_by_search_query_excluding_deactivated_and_unverified_lookalikes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Three REJECTED listings — these are the rows that may match.
        $cozyRejected = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Cozy 2-BR Apartment',
            'type'        => 'apartment',
            'barangay'    => 'carmen',
        ]);
        $cozyRejected->delete();
        ListingLifecycleEvent::factory()->create([
            'listing_id' => $cozyRejected->id,
            'actor_id'   => $admin->id,
            'event_type' => 'rejected',
            'reason'     => null,
            'notes'      => null,
            'created_at' => Carbon::now(),
        ]);

        $pueblo = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Modern Condo',
            'type'        => 'condo',
            'barangay'    => 'pueblo_de_oro',
        ]);
        $pueblo->delete();
        ListingLifecycleEvent::factory()->create([
            'listing_id' => $pueblo->id,
            'actor_id'   => $admin->id,
            'event_type' => 'rejected',
            'reason'     => null,
            'notes'      => null,
            'created_at' => Carbon::now(),
        ]);

        $spacious = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Spacious House',
            'type'        => 'house',
            'barangay'    => 'kauswagan',
        ]);
        $spacious->delete();
        ListingLifecycleEvent::factory()->create([
            'listing_id' => $spacious->id,
            'actor_id'   => $admin->id,
            'event_type' => 'rejected',
            'reason'     => null,
            'notes'      => null,
            'created_at' => Carbon::now(),
        ]);

        // Critical noise #1: a DEACTIVATED listing matching the same search keyword.
        // This is the lookalike that would silently slip through if the controller's
        // Scout branch dropped the `where('is_verified', 0)` clause (or used the
        // wrong false-case form like `false` or `'0'`).
        $cozyDeactivated = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(10),
            'title'       => 'Cozy Spam Listing',
            'type'        => 'apartment',
            'barangay'    => 'lapasan',
        ]);
        $cozyDeactivated->delete();

        // Critical noise #2: an UNVERIFIED-LIVE listing sharing the keyword.
        // The controller's `onlyTrashed()` MUST exclude it — same is_verified
        // flag as rejected, only the trashed status differs.
        Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Cozy Pending Studio',
            'type'        => 'studio',
            'barangay'    => 'lapasan',
        ]);

        // ---- Assertions ----

        // ?q=cozy must match ONLY the rejected cozy.
        // Deactivated `Cozy Spam Listing` and unverified-live `Cozy Pending Studio` excluded.
        $response = $this->get(route('admin.rejected-listings.index', ['q' => 'cozy']));
        $response->assertOk();
        $payload = $response->viewData('rejected');
        $this->assertCount(1, $payload['data'], 'Only the rejected Cozy must match — deactivated + unverified-live Cozy must be excluded');
        $this->assertSame($cozyRejected->id, $payload['data'][0]['id']);

        // ?q=pueblo — barangay slug substring match (Scout DB driver does single-string LIKE)
        $response = $this->get(route('admin.rejected-listings.index', ['q' => 'pueblo']));
        $payload  = $response->viewData('rejected');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($pueblo->id, $payload['data'][0]['id']);

        // ?q=spam — would match the deactivated `Cozy Spam Listing` if the slice
        // filter weren't applied. Must return 0 (deactivated ≠ rejected).
        $response = $this->get(route('admin.rejected-listings.index', ['q' => 'spam']));
        $this->assertCount(0, $response->viewData('rejected')['data'],
            'Deactivated listings must NOT appear on the rejected page — even when the search keyword matches');

        // ?q=pending — would match the unverified-live `Cozy Pending Studio` if
        // onlyTrashed() weren't applied. Must return 0 (unverified-live ≠ rejected).
        $response = $this->get(route('admin.rejected-listings.index', ['q' => 'pending']));
        $this->assertCount(0, $response->viewData('rejected')['data'],
            'Unverified-live listings must NOT appear on the rejected page — even when the search keyword matches');

        // Empty / missing q → all 3 rejected rows (deactivated + unverified-live excluded)
        $response = $this->get(route('admin.rejected-listings.index'));
        $this->assertSame(3, $response->viewData('rejected')['meta']['total']);

        // No match → empty
        $response = $this->get(route('admin.rejected-listings.index', ['q' => 'xyznomatch']));
        $this->assertCount(0, $response->viewData('rejected')['data']);
    }
}
