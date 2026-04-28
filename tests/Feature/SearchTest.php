<?php

namespace Tests\Feature;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_returns_only_verified_listings_sorted_by_verified_at_desc_with_expected_payload_shape(): void
    {
        $datesByCreationOrder = [
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(20),
            Carbon::now()->subDays(1),
            Carbon::now()->subDays(10),
        ];
        $verifiedIds = [];
        foreach ($datesByCreationOrder as $date) {
            $listing = Listing::factory()->withImages(2)->create([
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
                'is_verified' => true,
                'verified_at' => $date,
            ]);
            $verifiedIds[] = $listing->id;
        }

        Listing::factory()->count(2)->unverified()->create();

        $deactivated = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subHours(2),
        ]);
        $deactivated->delete();

        $rejected = Listing::factory()->unverified()->create();
        $rejected->delete();

        $response = $this->get(route('search'));
        $response->assertOk();

        $payload = $response->viewData('search');

        $this->assertEqualsCanonicalizing(
            ['listings', 'pagination', 'listingTypes', 'barangays', 'filters'],
            array_keys($payload)
        );

        $this->assertCount(4, $payload['listings']);

        $first = $payload['listings'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'title', 'type', 'type_label', 'price_monthly', 'beds', 'baths', 'sqm', 'barangay', 'barangay_label', 'image', 'image_count', 'section'],
            array_keys($first)
        );

        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        foreach ($payload['listings'] as $row) {
            $this->assertNotNull($row['image']);
            $this->assertGreaterThan(0, $row['image_count']);
        }

        $returnedIds = array_map(fn ($r) => $r['id'], $payload['listings']);
        $this->assertSame(
            [$verifiedIds[2], $verifiedIds[0], $verifiedIds[3], $verifiedIds[1]],
            $returnedIds,
            'Rows must be ordered by verified_at DESC'
        );

        foreach ($returnedIds as $id) {
            $this->assertContains($id, $verifiedIds);
        }

        $this->assertSame([
            'current_page' => 1,
            'last_page'    => 1,
            'per_page'     => 24,
            'total'        => 4,
            'from'         => 1,
            'to'           => 4,
        ], $payload['pagination']);

        $this->assertCount(count(ListingType::toValues()), $payload['listingTypes']);
        foreach ($payload['listingTypes'] as $type) {
            $this->assertEqualsCanonicalizing(['value', 'label'], array_keys($type));
            $this->assertContains($type['value'], ListingType::toValues());
            $this->assertSame(ListingType::from($type['value'])->label, $type['label']);
        }

        $this->assertCount(count(Barangay::toValues()), $payload['barangays']);
        foreach ($payload['barangays'] as $brgy) {
            $this->assertEqualsCanonicalizing(['value', 'label'], array_keys($brgy));
            $this->assertContains($brgy['value'], Barangay::toValues());
            $this->assertSame(Barangay::from($brgy['value'])->label, $brgy['label']);
        }
    }

    public function test_it_paginates_results_to_24_per_page_with_correct_pagination_meta_on_first_page(): void
    {
        Listing::factory()->count(30)->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->get(route('search'));
        $response->assertOk();

        $payload = $response->viewData('search');

        $this->assertCount(24, $payload['listings']);

        $this->assertSame([
            'current_page' => 1,
            'last_page'    => 2,
            'per_page'     => 24,
            'total'        => 30,
            'from'         => 1,
            'to'           => 24,
        ], $payload['pagination']);
    }

    public function test_it_filters_by_type_supporting_multi_select_via_csv(): void
    {
        // Fixture: 3 apartments + 2 condos + 1 studio + 1 house (verified-live);
        // 1 unverified apartment as noise.
        Listing::factory()->count(3)->withImages(1)->create([
            'type'        => 'apartment',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->count(2)->withImages(1)->create([
            'type'        => 'condo',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->withImages(1)->create([
            'type'        => 'studio',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->withImages(1)->create([
            'type'        => 'house',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->unverified()->create(['type' => 'apartment']);

        // (a) Single type — array of one
        $response = $this->get(route('search', ['type' => 'apartment']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame(['apartment'], $payload['filters']['type'],
            'filters.type must be an array of one when single type is requested');
        $this->assertSame(3, $payload['pagination']['total']);

        // (b) Multi-select via CSV — apartments + studios via OR semantics
        $response = $this->get(route('search', ['type' => 'apartment,studio']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(4, $payload['listings'], 'Multi-type CSV must include rows of every requested type');
        foreach ($payload['listings'] as $row) {
            $this->assertContains($row['type'], ['apartment', 'studio']);
        }
        $this->assertEqualsCanonicalizing(['apartment', 'studio'], $payload['filters']['type']);
        $this->assertSame(4, $payload['pagination']['total']);

        // (c) Partial-invalid CSV — invalid value silently dropped, valid kept
        $response = $this->get(route('search', ['type' => 'apartment,mansion']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame(['apartment'], $payload['filters']['type'],
            'Invalid type slugs must drop silently from the array');

        // (d) Duplicate values in CSV — deduped in filters block
        $response = $this->get(route('search', ['type' => 'apartment,apartment,studio']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(4, $payload['listings']);
        $this->assertEqualsCanonicalizing(['apartment', 'studio'], $payload['filters']['type']);
    }

    public function test_it_filters_by_barangay(): void
    {
        Listing::factory()->count(4)->withImages(1)->create([
            'barangay'    => 'pueblo_de_oro',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->count(2)->withImages(1)->create([
            'barangay'    => 'carmen',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->unverified()->create([
            'barangay' => 'pueblo_de_oro',
        ]);

        $response = $this->get(route('search', ['area' => 'pueblo_de_oro']));
        $response->assertOk();

        $payload = $response->viewData('search');

        $this->assertCount(4, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('pueblo_de_oro', $row['barangay']);
        }
        $this->assertSame('pueblo_de_oro', $payload['filters']['area']);
    }

    public function test_it_filters_by_budget_buckets(): void
    {
        $prices = [5000, 9999, 10000, 15000, 20000, 25000, 30000, 35000];
        foreach ($prices as $p) {
            Listing::factory()->withImages(1)->create([
                'price_monthly' => $p,
                'is_verified'   => true,
                'verified_at'   => Carbon::now(),
            ]);
        }

        // lt10k → strict < 10000
        $response = $this->get(route('search', ['budget' => 'lt10k']));
        $response->assertOk();
        $prices1 = collect($response->viewData('search')['listings'])->pluck('price_monthly')->all();
        sort($prices1);
        $this->assertSame([5000, 9999], $prices1, 'lt10k must NOT include 10000 (strict <)');

        // 10-20k → inclusive both bounds
        $response = $this->get(route('search', ['budget' => '10-20k']));
        $response->assertOk();
        $prices2 = collect($response->viewData('search')['listings'])->pluck('price_monthly')->all();
        sort($prices2);
        $this->assertSame([10000, 15000, 20000], $prices2, '10-20k must include both 10000 and 20000');

        // 20-30k → inclusive both bounds (overlaps with 10-20k at 20000)
        $response = $this->get(route('search', ['budget' => '20-30k']));
        $response->assertOk();
        $prices3 = collect($response->viewData('search')['listings'])->pluck('price_monthly')->all();
        sort($prices3);
        $this->assertSame([20000, 25000, 30000], $prices3, '20-30k must include both 20000 and 30000');

        // gt30k → strict > 30000
        $response = $this->get(route('search', ['budget' => 'gt30k']));
        $response->assertOk();
        $prices4 = collect($response->viewData('search')['listings'])->pluck('price_monthly')->all();
        $this->assertSame([35000], $prices4, 'gt30k must NOT include 30000 (strict >)');
    }

    public function test_it_applies_filters_when_q_is_explicitly_empty_or_whitespace(): void
    {
        // Pins: explicit ?q= (empty) and ?q=%20%20%20 (whitespace-only) trim to ''
        // and take the Eloquent path; filters apply normally; filters.q === ''.
        Listing::factory()->count(3)->withImages(1)->create([
            'type'        => 'apartment',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->count(2)->withImages(1)->create([
            'type'        => 'condo',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        // (a) Explicit empty q with a type filter
        $response = $this->get('/search?q=&type=apartment');
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame('', $payload['filters']['q'],
            'Explicit empty q must surface as empty string in filters block');
        $this->assertSame(['apartment'], $payload['filters']['type']);

        // (b) Whitespace-only q with a type filter
        $response = $this->get('/search?q=' . urlencode('   ') . '&type=apartment');
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame('', $payload['filters']['q'],
            'Whitespace q must trim to empty string in filters block');
        $this->assertSame(['apartment'], $payload['filters']['type']);

        // (c) Explicit empty q with multi filters (compose with non-q filters)
        $response = $this->get('/search?q=&type=apartment,condo');
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(5, $payload['listings']);
        $this->assertSame('', $payload['filters']['q']);
        $this->assertEqualsCanonicalizing(['apartment', 'condo'], $payload['filters']['type']);
    }

    public function test_it_filters_by_text_query_via_scout(): void
    {
        // Two verified listings with "cozy" in the title; three without.
        // Pins the Scout database-driver path: substring LIKE per indexed column.
        Listing::factory()->withImages(1)->create([
            'title'       => 'Cozy Studio in Carmen',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->withImages(1)->create([
            'title'       => 'Bright Cozy Studio',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->count(3)->withImages(1)->create([
            'title'       => 'Modern Apartment',
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $response = $this->get(route('search', ['q' => 'cozy']));
        $response->assertOk();

        $payload = $response->viewData('search');
        $listings = $payload['listings'];

        $this->assertCount(2, $listings);
        foreach ($listings as $listing) {
            $this->assertStringContainsStringIgnoringCase('cozy', $listing['title']);
        }
        $this->assertSame('cozy', $payload['filters']['q']);
    }

    public function test_it_composes_multiple_filters_with_and_semantics(): void
    {
        // Match-all row: apartment + carmen + 15000 (in 10-20k bucket)
        $matchAll = Listing::factory()->withImages(1)->create([
            'type'          => 'apartment',
            'barangay'      => 'carmen',
            'price_monthly' => 15000,
            'is_verified'   => true,
            'verified_at'   => Carbon::now(),
        ]);

        // Partial matches — each fails one of the three filters.
        Listing::factory()->withImages(1)->create([
            'type' => 'apartment', 'barangay' => 'pueblo_de_oro', 'price_monthly' => 15000,
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->withImages(1)->create([
            'type' => 'condo', 'barangay' => 'carmen', 'price_monthly' => 15000,
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->withImages(1)->create([
            'type' => 'apartment', 'barangay' => 'carmen', 'price_monthly' => 25000,
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        Listing::factory()->count(2)->withImages(1)->create([
            'type' => 'studio', 'barangay' => 'lapasan', 'price_monthly' => 5000,
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);

        $response = $this->get(route('search', [
            'type'   => 'apartment',
            'area'   => 'carmen',
            'budget' => '10-20k',
        ]));
        $response->assertOk();

        $payload = $response->viewData('search');

        $this->assertCount(1, $payload['listings']);
        $this->assertSame($matchAll->id, $payload['listings'][0]['id']);

        $this->assertSame(['apartment'], $payload['filters']['type']);
        $this->assertSame('carmen',      $payload['filters']['area']);
        $this->assertSame('10-20k',      $payload['filters']['budget']);
    }

    public function test_it_silently_drops_invalid_filter_values(): void
    {
        Listing::factory()->count(5)->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $response = $this->get(route('search', [
            'type'   => 'mansion',
            'area'   => 'fakebarangay',
            'budget' => 'invalid',
        ]));

        $response->assertOk(); // NOT 422

        $payload = $response->viewData('search');

        $this->assertCount(5, $payload['listings']);

        $this->assertSame(
            ['q' => '', 'budget' => null, 'area' => null, 'type' => []],
            $payload['filters'],
            'Invalid values must sanitize: q to empty string, budget/area to null, type to empty array'
        );
    }

    public function test_it_preserves_filters_on_pagination(): void
    {
        Listing::factory()->count(30)->withImages(1)->create([
            'type'        => 'apartment',
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(1),
        ]);
        Listing::factory()->count(5)->withImages(1)->create([
            'type'        => 'condo',
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->get(route('search', ['type' => 'apartment', 'page' => 2]));
        $response->assertOk();

        $payload = $response->viewData('search');

        // Spillover apartments — 24 on page 1, 6 on page 2.
        $this->assertCount(6, $payload['listings']);
        foreach ($payload['listings'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }

        // CRITICAL: total reflects FILTERED count, not unfiltered.
        // If appends() weren't there or filters were dropped, total would be 35.
        $this->assertSame(2,  $payload['pagination']['current_page']);
        $this->assertSame(2,  $payload['pagination']['last_page']);
        $this->assertSame(30, $payload['pagination']['total']);
        $this->assertSame(25, $payload['pagination']['from']);
        $this->assertSame(30, $payload['pagination']['to']);

        $this->assertSame(['apartment'], $payload['filters']['type']);
    }
}
