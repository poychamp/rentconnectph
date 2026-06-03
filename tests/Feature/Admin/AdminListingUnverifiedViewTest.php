<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingUnverifiedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.unverified-listings.index'));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_returns_paginated_unverified_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 15 unverified listings, spaced 1 day apart so created_at sort is unambiguous
        for ($i = 0; $i < 15; $i++) {
            Listing::factory()->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => 'unassigned',
                'created_at'   => Carbon::now()->subDays($i),
                'type'         => 'apartment',
                'barangay'     => 'pueblo_de_oro',
            ]);
        }

        // Noise that must NOT appear in the result
        Listing::factory()->create([
            'is_verified'  => true,
            'verified_at'  => Carbon::now(),
            'queue_status' => 'unassigned',
            'type'         => 'apartment',
            'barangay'     => 'pueblo_de_oro',
        ]);
        Listing::factory()
            ->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => 'unassigned',
                'type'         => 'apartment',
                'barangay'     => 'pueblo_de_oro',
            ])
            ->delete();

        // Page 1
        $response = $this->get(route('admin.unverified-listings.index'));
        $response->assertOk();

        // Single `unverified` view var: Laravel paginator + resource collection
        // already converted to { data: [...], meta: {...}, links: {...} } in the controller.
        $payload = $response->viewData('unverified');

        // Pagination meta — Laravel paginator standard
        $this->assertSame(1,  $payload['meta']['current_page']);
        $this->assertSame(2,  $payload['meta']['last_page']);
        $this->assertSame(15, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);

        // Data — page 1 = 10 rows
        $this->assertCount(10, $payload['data']);

        // Resource shape — keys + human-readable labels. Note `created_at` not `verified_at`.
        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label',
             'contact_phone',
             'prequal_status', 'prequal_status_label',
             'queue_status',   'queue_status_label',
             'assigned_to_name',
             'created_at', 'updated_at'],
            array_keys($first)
        );
        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        // Both timestamps must include time component (HH:MM:SS), not just date.
        // Admins need to see exactly when a submission was created and last touched.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['created_at'],
            'created_at must be an ISO 8601 datetime with time component, not a date-only string'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['updated_at'],
            'updated_at must be an ISO 8601 datetime with time component, not a date-only string'
        );

        // Sort order — newest created_at first (DESC). Per FRD-023, the unverified
        // queue surfaces newly-spotted leads first while they're still warm — calls
        // team works the most recent intake before stale leads.
        $createdAts = array_map(fn ($r) => $r['created_at'], $payload['data']);
        $expected = $createdAts;
        rsort($expected);
        $this->assertSame($expected, $createdAts, 'Rows must be ordered by created_at DESC');

        // Page 2 — remaining 5 rows
        $response2 = $this->get(route('admin.unverified-listings.index', ['page' => 2]));
        $response2->assertOk();
        $payload2 = $response2->viewData('unverified');
        $this->assertCount(5, $payload2['data']);
        $this->assertSame(2, $payload2['meta']['current_page']);
    }

    public function test_it_sorts_by_added_or_updated_and_ignores_q_when_sorting(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 4 unverified listings where created_at and updated_at are intentionally
        // INVERSE of each other — so created_at ASC vs updated_at ASC produce
        // different orders. Lets one fixture set verify all four sort variants.
        //
        // created_at ASC  → A, B, C, D    (A is oldest)
        // created_at DESC → D, C, B, A    (D is newest)
        // updated_at ASC  → D, C, B, A    (D is least-recently updated)
        // updated_at DESC → A, B, C, D    (A is most-recently updated)
        $a = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
            'title'        => 'Alpha Cozy',
            'created_at'   => Carbon::now()->subDays(4),
            'updated_at'   => Carbon::now()->subDays(1),
        ]);
        $b = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
            'title'        => 'Beta',
            'created_at'   => Carbon::now()->subDays(3),
            'updated_at'   => Carbon::now()->subDays(2),
        ]);
        $c = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
            'title'        => 'Gamma',
            'created_at'   => Carbon::now()->subDays(2),
            'updated_at'   => Carbon::now()->subDays(3),
        ]);
        $d = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
            'title'        => 'Delta',
            'created_at'   => Carbon::now()->subDays(1),
            'updated_at'   => Carbon::now()->subDays(4),
        ]);

        $idsInOrder = function (string $sort, string $dir) {
            $response = $this->get(route('admin.unverified-listings.index', ['sort' => $sort, 'dir' => $dir]));
            $response->assertOk();
            return array_map(fn ($r) => $r['id'], $response->viewData('unverified')['data']);
        };

        $this->assertSame([$a->id, $b->id, $c->id, $d->id], $idsInOrder('created_at', 'asc'),  'created_at ASC: A,B,C,D');
        $this->assertSame([$d->id, $c->id, $b->id, $a->id], $idsInOrder('created_at', 'desc'), 'created_at DESC: D,C,B,A');
        $this->assertSame([$d->id, $c->id, $b->id, $a->id], $idsInOrder('updated_at', 'asc'),  'updated_at ASC: D,C,B,A');
        $this->assertSame([$a->id, $b->id, $c->id, $d->id], $idsInOrder('updated_at', 'desc'), 'updated_at DESC: A,B,C,D');

        // CRITICAL: when sort is present, q MUST be ignored. Sorting forces the
        // Eloquent path because Algolia can't sort by arbitrary fields at query
        // time (orderBy is a no-op against Algolia — see CLAUDE.md). Mixing q
        // and sort would silently apply only one of them; we explicitly drop q.
        $response = $this->get(route('admin.unverified-listings.index', [
            'sort' => 'created_at',
            'dir'  => 'desc',
            'q'    => 'cozy',  // matches only $a — but must be IGNORED because sort is present
        ]));
        $response->assertOk();
        $ids = array_map(fn ($r) => $r['id'], $response->viewData('unverified')['data']);
        $this->assertSame(
            [$d->id, $c->id, $b->id, $a->id],
            $ids,
            'Sort must beat q — all 4 unverified rows returned in created_at DESC order, not just the q=cozy match'
        );
    }

    public function test_it_excludes_listings_with_queue_status_other_than_unassigned_or_assigned(): void
    {
        // Once a listing has been visited (field officer hit Request Verification),
        // it moves to the field officer's "submitted" surface and the admin queue
        // shouldn't surface it anymore — calls team has nothing to do until admin
        // verifies. Same for dead — already triaged out. Only `unassigned` (fresh
        // intake) and `assigned` (called + handed to a field officer) belong here.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $unassigned = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
        ]);
        $assigned = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'assigned',
        ]);
        $visited = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'visited',
        ]);
        $dead = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'dead',
        ]);

        $response = $this->get(route('admin.unverified-listings.index'));
        $ids = collect($response->viewData('unverified')['data'])->pluck('id')->all();

        $this->assertContains($unassigned->id, $ids);
        $this->assertContains($assigned->id,   $ids);
        $this->assertNotContains($visited->id, $ids);
        $this->assertNotContains($dead->id,    $ids);
    }

    public function test_it_excludes_listings_with_null_queue_status(): void
    {
        // Per FRD-023, every queue listing gets `queue_status='unassigned'` on create.
        // NULL queue_status is either legacy/pre-FRD-023 data or direct-DB tampering —
        // not a state the calls team should action. Strict slice excludes it too.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $unassigned = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
        ]);
        $nullStatus = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => null,
        ]);

        $response = $this->get(route('admin.unverified-listings.index'));
        $ids = collect($response->viewData('unverified')['data'])->pluck('id')->all();

        $this->assertContains($unassigned->id, $ids);
        $this->assertNotContains($nullStatus->id, $ids);
    }

    public function test_it_excludes_visited_dead_and_null_queue_status_on_scout_search_path(): void
    {
        // The Scout path (q != '') has its own where-chain, separate from the
        // Eloquent path. Both must enforce the queue_status slice. Title-pinned
        // 'Pueblo plaza' across all 5 fixtures so the LIKE %pueblo% matches each;
        // queue_status filter is the only thing that should determine visibility.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $unassigned = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'unassigned',
            'title'        => 'Pueblo plaza unassigned',
            'barangay'     => 'lapasan',
        ]);
        $assigned = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'assigned',
            'title'        => 'Pueblo plaza assigned',
            'barangay'     => 'lapasan',
        ]);
        $visited = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'visited',
            'title'        => 'Pueblo plaza visited',
            'barangay'     => 'lapasan',
        ]);
        $dead = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => 'dead',
            'title'        => 'Pueblo plaza dead',
            'barangay'     => 'lapasan',
        ]);
        $nullStatus = Listing::factory()->create([
            'is_verified'  => false, 'verified_at' => null,
            'queue_status' => null,
            'title'        => 'Pueblo plaza null',
            'barangay'     => 'lapasan',
        ]);

        $response = $this->get(route('admin.unverified-listings.index', ['q' => 'pueblo']));
        $ids = collect($response->viewData('unverified')['data'])->pluck('id')->all();

        $this->assertContains($unassigned->id,    $ids);
        $this->assertContains($assigned->id,      $ids);
        $this->assertNotContains($visited->id,    $ids);
        $this->assertNotContains($dead->id,       $ids);
        $this->assertNotContains($nullStatus->id, $ids);
    }

    public function test_it_includes_queue_state_fields_in_resource(): void
    {
        // Two rows: one with explicit prequal/queue state, one with null state
        // (pre-FRD-023 records or fresh factory rows). Both label fields must
        // null-guard the enum-from-value lookup.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $statusFul = Listing::factory()->create([
            'is_verified'    => false,
            'verified_at'    => null,
            'prequal_status' => 'called_yes',
            'queue_status'   => 'unassigned',
        ]);

        // queue_status is now slice-constrained to {'unassigned','assigned'} —
        // null queue_status no longer surfaces here. prequal_status nullability
        // is still load-bearing (prequal happens after the row enters the queue
        // with status='unassigned' but before the calls team has dialed).
        $prequalNull = Listing::factory()->create([
            'is_verified'    => false,
            'verified_at'    => null,
            'prequal_status' => null,
            'queue_status'   => 'unassigned',
        ]);

        $response = $this->get(route('admin.unverified-listings.index'));
        $rows = $response->viewData('unverified')['data'];

        $row1 = collect($rows)->firstWhere('id', $statusFul->id);
        $this->assertSame('called_yes', $row1['prequal_status']);
        $this->assertSame('Called',     $row1['prequal_status_label']);
        $this->assertSame('unassigned', $row1['queue_status']);
        $this->assertSame('Unassigned', $row1['queue_status_label']);
        $this->assertNull($row1['assigned_to_name']);

        $row2 = collect($rows)->firstWhere('id', $prequalNull->id);
        $this->assertNull($row2['prequal_status']);
        $this->assertNull($row2['prequal_status_label']);
        $this->assertSame('unassigned', $row2['queue_status']);
        $this->assertSame('Unassigned', $row2['queue_status_label']);
        $this->assertNull($row2['assigned_to_name']);
    }

    public function test_it_returns_contact_phone_in_resource(): void
    {
        // FRD-023 § 3.7 — calls team needs the phone number visible in the queue
        // table so they can dial without opening the row. Resource ships the
        // E.164 form as stored; presentation-layer formatting is the frontend's
        // job (e.g. 0917 123 4567).
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $contact = ListingContact::factory()->create(['phone' => '+639171234567']);
        $listing = Listing::factory()->create([
            'is_verified'        => false,
            'verified_at'        => null,
            'queue_status'       => 'unassigned',
            'listing_contact_id' => $contact->id,
        ]);

        $response = $this->get(route('admin.unverified-listings.index'));
        $row = collect($response->viewData('unverified')['data'])
            ->firstWhere('id', $listing->id);

        $this->assertSame('+639171234567', $row['contact_phone']);
    }

    public function test_it_returns_assigned_user_name_when_listing_is_assigned(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $field = User::factory()->create(['name' => 'Maria Cruz']);

        $assigned = Listing::factory()->create([
            'is_verified'    => false,
            'verified_at'    => null,
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $field->id,
        ]);

        $response = $this->get(route('admin.unverified-listings.index'));
        $rows = $response->viewData('unverified')['data'];

        $row = collect($rows)->firstWhere('id', $assigned->id);
        $this->assertSame('Maria Cruz', $row['assigned_to_name']);
    }

    public function test_it_filters_by_search_query(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $cozyUnverified = Listing::factory()->create([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => 'unassigned',
            'title'        => 'Cozy 2-BR Apartment',
            'type'         => 'apartment',
            'barangay'     => 'carmen',
        ]);

        $pueblo = Listing::factory()->create([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => 'unassigned',
            'title'        => 'Modern Condo',
            'type'         => 'condo',
            'barangay'     => 'pueblo_de_oro',
        ]);

        Listing::factory()->create([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => 'unassigned',
            'title'        => 'Spacious House',
            'type'         => 'house',
            'barangay'     => 'kauswagan',
        ]);

        // Critical noise: a verified listing matching the same search keyword.
        // The controller's `where('is_verified', '0')` MUST exclude this.
        // If this assertion fails, the implementation likely passed `false`
        // instead of `'0'` to Scout's where() — Scout quotes `false` as `''`,
        // which silently misses the indexed `'0'`.
        Listing::factory()->create([
            'is_verified'  => true,
            'verified_at'  => Carbon::now(),
            'queue_status' => 'unassigned',
            'title'        => 'Cozy Verified Studio',
            'type'         => 'studio',
            'barangay'     => 'lapasan',
        ]);

        // Title fragment — must match the unverified Cozy, NOT the verified Cozy
        $response = $this->get(route('admin.unverified-listings.index', ['q' => 'cozy']));
        $payload  = $response->viewData('unverified');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($cozyUnverified->id, $payload['data'][0]['id']);

        // Barangay slug fragment (substring match on the column value)
        $response = $this->get(route('admin.unverified-listings.index', ['q' => 'pueblo']));
        $payload  = $response->viewData('unverified');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($pueblo->id, $payload['data'][0]['id']);

        // Empty / missing q → all 3 unverified rows (verified excluded)
        $response = $this->get(route('admin.unverified-listings.index'));
        $this->assertSame(3, $response->viewData('unverified')['meta']['total']);

        // No match → empty
        $response = $this->get(route('admin.unverified-listings.index', ['q' => 'xyznomatch']));
        $this->assertCount(0, $response->viewData('unverified')['data']);
    }
}
