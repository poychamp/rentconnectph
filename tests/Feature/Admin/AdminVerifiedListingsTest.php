<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminVerifiedListingsTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.verified-listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_paginated_verified_listings_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 15 verified listings. verified_at and updated_at are intentionally
        // INVERSE of each other so the default sort (updated_at DESC) returns
        // a visibly different order than verified_at DESC would.
        // i=0  → verified_at = today,            updated_at = 15 days ago (oldest update)
        // i=14 → verified_at = 14 days ago,      updated_at = 1 day ago   (newest update)
        for ($i = 0; $i < 15; $i++) {
            Listing::factory()->create([
                'is_verified' => true,
                'verified_at' => Carbon::now()->subDays($i),
                'updated_at'  => Carbon::now()->subDays(15 - $i),
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ]);
        }

        // Noise that must NOT appear in the result
        Listing::factory()->create([
            'is_verified' => false,
            'type'        => 'apartment',
            'barangay'    => 'pueblo_de_oro',
        ]);
        Listing::factory()
            ->create([
                'is_verified' => true,
                'verified_at' => Carbon::now(),
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
            ])
            ->delete();

        // Page 1
        $response = $this->get(route('admin.verified-listings.index'));
        $response->assertOk();

        // Single `verified` view var: Laravel paginator + resource collection
        // already converted to { data: [...], meta: {...}, links: {...} } in the controller
        $payload = $response->viewData('verified');

        // Pagination meta — Laravel paginator standard
        $this->assertSame(1,  $payload['meta']['current_page']);
        $this->assertSame(2,  $payload['meta']['last_page']);
        $this->assertSame(15, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);

        // Data — page 1 = 10 rows
        $this->assertCount(10, $payload['data']);

        // Resource shape — keys + human-readable labels
        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label', 'price', 'verified_at', 'updated_at'],
            array_keys($first)
        );
        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        // verified_at + updated_at must include time component (HH:MM:SS), not just date.
        // Admins need to see exactly when a listing was verified and last touched.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['verified_at'],
            'verified_at must be an ISO 8601 datetime with time component, not a date-only string'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $first['updated_at'],
            'updated_at must be an ISO 8601 datetime with time component, not a date-only string'
        );

        // Sort order — newest updated_at first (DESC). Verified Listings is
        // an "active catalog" view: most-recently-touched listings rise to the
        // top, regardless of when they were originally verified. ISO 8601
        // strings sort lexicographically the same as chronologically.
        $updatedAts = array_map(fn ($r) => $r['updated_at'], $payload['data']);
        $expected = $updatedAts;
        rsort($expected);
        $this->assertSame($expected, $updatedAts, 'Rows must be ordered by updated_at DESC');

        // Page 2 — remaining 5 rows
        $response2 = $this->get(route('admin.verified-listings.index', ['page' => 2]));
        $response2->assertOk();
        $payload2 = $response2->viewData('verified');
        $this->assertCount(5, $payload2['data']);
        $this->assertSame(2, $payload2['meta']['current_page']);
    }

    public function test_it_filters_by_search_query(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $cozy = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'title'       => 'Cozy 2-BR Apartment',
            'type'        => 'apartment',
            'barangay'    => 'carmen',
        ]);

        $pueblo = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'title'       => 'Modern Condo',
            'type'        => 'condo',
            'barangay'    => 'pueblo_de_oro',
        ]);

        Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'title'       => 'Spacious House',
            'type'        => 'house',
            'barangay'    => 'kauswagan',
        ]);

        // Title fragment
        $response = $this->get(route('admin.verified-listings.index', ['q' => 'cozy']));
        $payload  = $response->viewData('verified');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($cozy->id, $payload['data'][0]['id']);

        // Barangay slug fragment (substring match on the column value)
        $response = $this->get(route('admin.verified-listings.index', ['q' => 'pueblo']));
        $payload  = $response->viewData('verified');
        $this->assertCount(1, $payload['data']);
        $this->assertSame($pueblo->id, $payload['data'][0]['id']);

        // Empty / missing q → all rows (no filter)
        $response = $this->get(route('admin.verified-listings.index'));
        $this->assertSame(3, $response->viewData('verified')['meta']['total']);

        // No match → empty
        $response = $this->get(route('admin.verified-listings.index', ['q' => 'xyznomatch']));
        $this->assertCount(0, $response->viewData('verified')['data']);
    }
}
