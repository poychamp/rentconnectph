<?php

namespace Tests\Feature;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_returns_only_verified_listings_sorted_by_listed_at_desc_with_expected_payload_shape(): void
    {
        // verified_at is shared across all 4 rows so it can't be the accidental sort key —
        // a verified_at DESC sort would tie and fall back to DB insertion order, which
        // would NOT match the expected listed_at DESC return order.
        $sharedVerifiedAt = Carbon::parse('2026-01-01 00:00:00');
        $listedDatesByCreationOrder = [
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(20),
            Carbon::now()->subDays(1),
            Carbon::now()->subDays(10),
        ];
        $verifiedIds = [];
        foreach ($listedDatesByCreationOrder as $date) {
            $listing = Listing::factory()->withImages(2)->create([
                'type'        => 'apartment',
                'barangay'    => 'pueblo_de_oro',
                'is_verified' => true,
                'verified_at' => $sharedVerifiedAt,
                'listed_at'   => $date,
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
            ['data', 'links', 'meta', 'filters', 'catalogs'],
            array_keys($payload)
        );

        $this->assertCount(4, $payload['data']);

        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'title', 'type', 'type_label', 'price_monthly', 'beds', 'baths', 'sqm', 'barangay', 'barangay_label', 'image', 'image_count', 'section'],
            array_keys($first)
        );

        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        foreach ($payload['data'] as $row) {
            $this->assertNotNull($row['image']);
            $this->assertGreaterThan(0, $row['image_count']);
        }

        $returnedIds = array_map(fn ($r) => $r['id'], $payload['data']);
        $this->assertSame(
            [$verifiedIds[2], $verifiedIds[0], $verifiedIds[3], $verifiedIds[1]],
            $returnedIds,
            'Rows must be ordered by listed_at DESC'
        );

        foreach ($returnedIds as $id) {
            $this->assertContains($id, $verifiedIds);
        }

        // meta carries the 6 canonical pagination keys (plus Laravel's path + inner links — not pinned).
        // Key order matches Laravel's ResourceCollection::paginationInformation() output.
        $this->assertSame([
            'current_page' => 1,
            'from'         => 1,
            'last_page'    => 1,
            'per_page'     => 24,
            'to'           => 4,
            'total'        => 4,
        ], Arr::only($payload['meta'], ['current_page', 'from', 'last_page', 'per_page', 'to', 'total']));

        // Top-level links is Laravel's pagination link block
        $this->assertEqualsCanonicalizing(
            ['first', 'last', 'prev', 'next'],
            array_keys($payload['links'])
        );

        // Catalogs nested under catalogs key, snake_case
        $this->assertEqualsCanonicalizing(
            ['listing_types', 'barangays'],
            array_keys($payload['catalogs'])
        );

        $this->assertCount(count(ListingType::toValues()), $payload['catalogs']['listing_types']);
        foreach ($payload['catalogs']['listing_types'] as $type) {
            $this->assertEqualsCanonicalizing(['value', 'label'], array_keys($type));
            $this->assertContains($type['value'], ListingType::toValues());
            $this->assertSame(ListingType::from($type['value'])->label, $type['label']);
        }

        $this->assertCount(count(Barangay::toValues()), $payload['catalogs']['barangays']);
        foreach ($payload['catalogs']['barangays'] as $brgy) {
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

        $this->assertCount(24, $payload['data']);

        $this->assertSame([
            'current_page' => 1,
            'from'         => 1,
            'last_page'    => 2,
            'per_page'     => 24,
            'to'           => 24,
            'total'        => 30,
        ], Arr::only($payload['meta'], ['current_page', 'from', 'last_page', 'per_page', 'to', 'total']));
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
        $this->assertCount(3, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame(['apartment'], $payload['filters']['type'],
            'filters.type must be an array of one when single type is requested');
        $this->assertSame(3, $payload['meta']['total']);

        // (b) Multi-select via CSV — apartments + studios via OR semantics
        $response = $this->get(route('search', ['type' => 'apartment,studio']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(4, $payload['data'], 'Multi-type CSV must include rows of every requested type');
        foreach ($payload['data'] as $row) {
            $this->assertContains($row['type'], ['apartment', 'studio']);
        }
        $this->assertEqualsCanonicalizing(['apartment', 'studio'], $payload['filters']['type']);
        $this->assertSame(4, $payload['meta']['total']);

        // (c) Partial-invalid CSV — invalid value silently dropped, valid kept
        $response = $this->get(route('search', ['type' => 'apartment,mansion']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame(['apartment'], $payload['filters']['type'],
            'Invalid type slugs must drop silently from the array');

        // (d) Duplicate values in CSV — deduped in filters block
        $response = $this->get(route('search', ['type' => 'apartment,apartment,studio']));
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(4, $payload['data']);
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

        $this->assertCount(4, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('pueblo_de_oro', $row['barangay']);
        }
        $this->assertSame('pueblo_de_oro', $payload['filters']['area']);
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
        $this->assertCount(3, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame('', $payload['filters']['q'],
            'Explicit empty q must surface as empty string in filters block');
        $this->assertSame(['apartment'], $payload['filters']['type']);

        // (b) Whitespace-only q with a type filter
        $response = $this->get('/search?q=' . urlencode('   ') . '&type=apartment');
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }
        $this->assertSame('', $payload['filters']['q'],
            'Whitespace q must trim to empty string in filters block');
        $this->assertSame(['apartment'], $payload['filters']['type']);

        // (c) Explicit empty q with multi filters (compose with non-q filters)
        $response = $this->get('/search?q=&type=apartment,condo');
        $response->assertOk();
        $payload = $response->viewData('search');
        $this->assertCount(5, $payload['data']);
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
        $listings = $payload['data'];

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
            'type'       => 'apartment',
            'area'       => 'carmen',
            'budget_min' => 10000,
            'budget_max' => 20000,
        ]));
        $response->assertOk();

        $payload = $response->viewData('search');

        $this->assertCount(1, $payload['data']);
        $this->assertSame($matchAll->id, $payload['data'][0]['id']);

        $this->assertSame(['apartment'], $payload['filters']['type']);
        $this->assertSame('carmen',      $payload['filters']['area']);
        $this->assertSame(10000,         $payload['filters']['budget_min']);
        $this->assertSame(20000,         $payload['filters']['budget_max']);
    }

    public function test_it_silently_drops_invalid_filter_values(): void
    {
        Listing::factory()->count(5)->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $response = $this->get(route('search', [
            'type'       => 'mansion',
            'area'       => 'fakebarangay',
            'budget_min' => 'junk',
            'budget_max' => 'junk',
        ]));

        $response->assertOk(); // NOT 422

        $payload = $response->viewData('search');

        $this->assertCount(5, $payload['data']);

        $this->assertSame(
            ['q' => '', 'budget_min' => null, 'budget_max' => null, 'area' => null, 'type' => []],
            $payload['filters'],
            'Invalid values must sanitize: q to empty string, bounds + area to null, type to empty array'
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
        $this->assertCount(6, $payload['data']);
        foreach ($payload['data'] as $row) {
            $this->assertSame('apartment', $row['type']);
        }

        // CRITICAL: total reflects FILTERED count, not unfiltered.
        // If appends() weren't there or filters were dropped, total would be 35.
        $this->assertSame(2,  $payload['meta']['current_page']);
        $this->assertSame(2,  $payload['meta']['last_page']);
        $this->assertSame(30, $payload['meta']['total']);
        $this->assertSame(25, $payload['meta']['from']);
        $this->assertSame(30, $payload['meta']['to']);

        $this->assertSame(['apartment'], $payload['filters']['type']);
    }

    // =====================================================================
    // FRD-036 — range-filter cases (?budget_min, ?budget_max)
    // =====================================================================

    private function seedBudgetFixture(): void
    {
        $prices = [5000, 10000, 15000, 20000, 25000];
        foreach ($prices as $p) {
            Listing::factory()->withImages(1)->create([
                'price_monthly' => $p,
                'is_verified'   => true,
                'verified_at'   => Carbon::now(),
            ]);
        }
    }

    public function test_it_filters_by_budget_min_only(): void
    {
        $this->seedBudgetFixture();

        $response = $this->get(route('search', ['budget_min' => 15000]));
        $response->assertOk();

        $payload = $response->viewData('search');
        $prices = collect($payload['data'])->pluck('price_monthly')->sort()->values()->all();

        $this->assertSame([15000, 20000, 25000], $prices, 'budget_min uses inclusive >= comparison');
        $this->assertSame(15000, $payload['filters']['budget_min']);
        $this->assertNull($payload['filters']['budget_max']);
    }

    public function test_it_filters_by_budget_max_only(): void
    {
        $this->seedBudgetFixture();

        $response = $this->get(route('search', ['budget_max' => 15000]));
        $response->assertOk();

        $payload = $response->viewData('search');
        $prices = collect($payload['data'])->pluck('price_monthly')->sort()->values()->all();

        $this->assertSame([5000, 10000, 15000], $prices, 'budget_max uses inclusive <= comparison');
        $this->assertNull($payload['filters']['budget_min']);
        $this->assertSame(15000, $payload['filters']['budget_max']);
    }

    public function test_it_filters_by_both_budget_min_and_max(): void
    {
        $this->seedBudgetFixture();

        $response = $this->get(route('search', [
            'budget_min' => 10000,
            'budget_max' => 20000,
        ]));
        $response->assertOk();

        $payload = $response->viewData('search');
        $prices = collect($payload['data'])->pluck('price_monthly')->sort()->values()->all();

        $this->assertSame([10000, 15000, 20000], $prices);
        $this->assertSame(10000, $payload['filters']['budget_min']);
        $this->assertSame(20000, $payload['filters']['budget_max']);
    }

    public function test_it_silently_drops_malformed_budget_bound_values(): void
    {
        $this->seedBudgetFixture();

        // 'abc' = non-digit; '-500' = negative (ctype_digit rejects '-'). Both must drop.
        $response = $this->get(route('search', [
            'budget_min' => 'abc',
            'budget_max' => '-500',
        ]));
        $response->assertOk();

        $payload = $response->viewData('search');
        $this->assertCount(5, $payload['data'], 'Malformed bounds drop silently → no filter applied');
        $this->assertNull($payload['filters']['budget_min']);
        $this->assertNull($payload['filters']['budget_max']);
    }

    public function test_it_swaps_inverted_bounds_when_min_exceeds_max(): void
    {
        $this->seedBudgetFixture();

        $response = $this->get(route('search', [
            'budget_min' => 20000,
            'budget_max' => 5000,
        ]));
        $response->assertOk();

        $payload = $response->viewData('search');
        $prices = collect($payload['data'])->pluck('price_monthly')->sort()->values()->all();

        // After swap → effective range [5000, 20000] inclusive.
        $this->assertSame([5000, 10000, 15000, 20000], $prices);
        $this->assertSame(5000,  $payload['filters']['budget_min'], 'filters surface the swapped values');
        $this->assertSame(20000, $payload['filters']['budget_max']);
    }

    // =====================================================================
    // FRD-036 — legacy 4-bucket ?budget= 301 redirect-translate
    // =====================================================================

    private function assertRedirectsToSearchWith301(array $expectedParams, $response): void
    {
        $response->assertStatus(301);
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, 'Redirect must set a Location header');

        $parsed = parse_url($location);
        $this->assertSame('/search', $parsed['path'] ?? null, 'Redirect target must be /search');

        $actual = [];
        parse_str($parsed['query'] ?? '', $actual);
        $this->assertEquals($expectedParams, $actual, 'Redirect query params must match (order-agnostic)');
    }

    public function test_it_redirects_legacy_budget_lt10k_to_budget_max_9999_with_301(): void
    {
        $response = $this->get('/search?budget=lt10k');
        $this->assertRedirectsToSearchWith301(['budget_max' => '9999'], $response);
    }

    public function test_it_redirects_legacy_budget_10_20k_to_budget_min_max_with_301(): void
    {
        $response = $this->get('/search?budget=10-20k');
        $this->assertRedirectsToSearchWith301([
            'budget_min' => '10000',
            'budget_max' => '20000',
        ], $response);
    }

    public function test_it_redirects_legacy_budget_20_30k_to_budget_min_max_with_301(): void
    {
        $response = $this->get('/search?budget=20-30k');
        $this->assertRedirectsToSearchWith301([
            'budget_min' => '20000',
            'budget_max' => '30000',
        ], $response);
    }

    public function test_it_redirects_legacy_budget_gt30k_to_budget_min_30001_with_301(): void
    {
        $response = $this->get('/search?budget=gt30k');
        $this->assertRedirectsToSearchWith301(['budget_min' => '30001'], $response);
    }

    public function test_it_preserves_other_query_params_on_legacy_budget_redirect(): void
    {
        $response = $this->get('/search?budget=10-20k&q=apartment&area=carmen&type=apartment,studio');
        $this->assertRedirectsToSearchWith301([
            'q'          => 'apartment',
            'area'       => 'carmen',
            'type'       => 'apartment,studio',
            'budget_min' => '10000',
            'budget_max' => '20000',
        ], $response);
    }

    public function test_it_strips_legacy_budget_param_when_new_params_also_present(): void
    {
        // ?budget=lt10k AND ?budget_min=8000 — legacy gets stripped, new param preserved
        // as-is. No enrichment from the legacy mapping (the user manually crafted intent).
        $response = $this->get('/search?budget=lt10k&budget_min=8000');
        $this->assertRedirectsToSearchWith301(['budget_min' => '8000'], $response);
    }

    public function test_it_does_not_redirect_when_legacy_budget_value_is_unknown(): void
    {
        Listing::factory()->count(3)->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        // Unknown enum value silently drops at validBudget (current) or matches no key
        // in LEGACY_BUDGET_RANGES (post-impl). Either way: 200, no redirect.
        $response = $this->get('/search?budget=foo');
        $response->assertOk();
        $response->assertStatus(200);

        $payload = $response->viewData('search');
        $this->assertCount(3, $payload['data']);
        $this->assertNull($payload['filters']['budget_min']);
        $this->assertNull($payload['filters']['budget_max']);
    }
}
