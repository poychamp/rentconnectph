<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminUnverifiedListingsTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.unverified-listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_paginated_unverified_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 15 unverified listings, spaced 1 day apart so created_at sort is unambiguous
        for ($i = 0; $i < 15; $i++) {
            Listing::factory()->create([
                'is_verified' => false,
                'verified_at' => null,
                'created_at'  => Carbon::now()->subDays($i),
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ]);
        }

        // Noise that must NOT appear in the result
        Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'type'        => 'apartment',
            'barangay'    => 'pueblo_de_oro',
        ]);
        Listing::factory()
            ->create([
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
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
            ['id', 'uuid', 'name', 'type_label', 'barangay_label', 'price', 'created_at', 'updated_at'],
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

        // Sort order — oldest created_at first (ASC). Unverified is a FIFO triage
        // queue — listings waiting longest get reviewed first.
        $createdAts = array_map(fn ($r) => $r['created_at'], $payload['data']);
        $expected = $createdAts;
        sort($expected);
        $this->assertSame($expected, $createdAts, 'Rows must be ordered by created_at ASC');

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
            'is_verified' => false, 'verified_at' => null,
            'title'       => 'Alpha Cozy',
            'created_at'  => Carbon::now()->subDays(4),
            'updated_at'  => Carbon::now()->subDays(1),
        ]);
        $b = Listing::factory()->create([
            'is_verified' => false, 'verified_at' => null,
            'title'       => 'Beta',
            'created_at'  => Carbon::now()->subDays(3),
            'updated_at'  => Carbon::now()->subDays(2),
        ]);
        $c = Listing::factory()->create([
            'is_verified' => false, 'verified_at' => null,
            'title'       => 'Gamma',
            'created_at'  => Carbon::now()->subDays(2),
            'updated_at'  => Carbon::now()->subDays(3),
        ]);
        $d = Listing::factory()->create([
            'is_verified' => false, 'verified_at' => null,
            'title'       => 'Delta',
            'created_at'  => Carbon::now()->subDays(1),
            'updated_at'  => Carbon::now()->subDays(4),
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

    public function test_it_filters_by_search_query(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $cozyUnverified = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Cozy 2-BR Apartment',
            'type'        => 'apartment',
            'barangay'    => 'carmen',
        ]);

        $pueblo = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Modern Condo',
            'type'        => 'condo',
            'barangay'    => 'pueblo_de_oro',
        ]);

        Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
            'title'       => 'Spacious House',
            'type'        => 'house',
            'barangay'    => 'kauswagan',
        ]);

        // Critical noise: a verified listing matching the same search keyword.
        // The controller's `where('is_verified', '0')` MUST exclude this.
        // If this assertion fails, the implementation likely passed `false`
        // instead of `'0'` to Scout's where() — Scout quotes `false` as `''`,
        // which silently misses the indexed `'0'`.
        Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'title'       => 'Cozy Verified Studio',
            'type'        => 'studio',
            'barangay'    => 'lapasan',
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
