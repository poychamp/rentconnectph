<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class SearchFetchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear($this->throttleKey('api-search', 'minute:127.0.0.1'));
        RateLimiter::clear($this->throttleKey('api-search', 'hour:127.0.0.1'));
    }

    // Laravel's ThrottleRequests middleware md5-hashes the cache key as
    // md5($limiterName . $limit->key) before storing. Tests pre-filling the
    // bucket must match that derivation.
    private function throttleKey(string $limiter, string $byKey): string
    {
        return md5($limiter.$byKey);
    }

    private function endpoint(array $query = []): string
    {
        return '/api/v1/search' . ($query ? '?' . http_build_query($query) : '');
    }

    // =====================================================================
    // Auth
    // =====================================================================

    public function test_it_allows_guest_with_no_token(): void
    {
        $this->getJson($this->endpoint())->assertOk();
    }

    // =====================================================================
    // Slice scope
    // =====================================================================

    public function test_it_excludes_unverified_listings(): void
    {
        $verified   = Listing::factory()->create(['is_verified' => true]);
        $unverified = Listing::factory()->unverified()->create();

        $uuids = collect($this->getJson($this->endpoint())->json('data'))->pluck('uuid');

        $this->assertContains($verified->uuid,    $uuids);
        $this->assertNotContains($unverified->uuid, $uuids);
    }

    public function test_it_excludes_soft_deleted_listings(): void
    {
        $live    = Listing::factory()->create(['is_verified' => true]);
        $deleted = Listing::factory()->create(['is_verified' => true]);
        $deleted->delete();

        $uuids = collect($this->getJson($this->endpoint())->json('data'))->pluck('uuid');

        $this->assertContains($live->uuid,        $uuids);
        $this->assertNotContains($deleted->uuid,  $uuids);
    }

    // =====================================================================
    // Browse mode (no q)
    // =====================================================================

    public function test_browse_orders_by_listed_at_desc(): void
    {
        $oldest = Listing::factory()->create(['is_verified' => true, 'listed_at' => Carbon::parse('2026-04-01 10:00:00')]);
        $middle = Listing::factory()->create(['is_verified' => true, 'listed_at' => Carbon::parse('2026-04-15 10:00:00')]);
        $newest = Listing::factory()->create(['is_verified' => true, 'listed_at' => Carbon::parse('2026-04-30 10:00:00')]);

        $uuids = collect($this->getJson($this->endpoint())->json('data'))->pluck('uuid')->all();

        $this->assertSame([$newest->uuid, $middle->uuid, $oldest->uuid], $uuids);
    }

    public function test_browse_paginates_at_24_per_page(): void
    {
        Listing::factory()->count(25)->create(['is_verified' => true]);

        $page1 = $this->getJson($this->endpoint());
        $page2 = $this->getJson($this->endpoint(['page' => 2]));

        $this->assertCount(24, $page1->json('data'));
        $this->assertCount(1,  $page2->json('data'));
        $this->assertSame(25,  $page1->json('meta.total'));
        $this->assertSame(24,  $page1->json('meta.per_page'));
        $this->assertSame(1,   $page1->json('meta.current_page'));
        $this->assertSame(2,   $page2->json('meta.current_page'));
    }

    // =====================================================================
    // Search query (q)
    // =====================================================================

    public function test_q_filters_by_title_substring(): void
    {
        $hit  = Listing::factory()->create(['is_verified' => true, 'title' => 'Sunset Studio Suites',     'description' => 'Quiet hilltop view']);
        $miss = Listing::factory()->create(['is_verified' => true, 'title' => 'Mountain View Apartments', 'description' => 'Quiet hilltop view']);

        $uuids = collect($this->getJson($this->endpoint(['q' => 'sunset']))->json('data'))->pluck('uuid');

        $this->assertContains($hit->uuid,    $uuids);
        $this->assertNotContains($miss->uuid, $uuids);
    }

    // =====================================================================
    // Filter narrowing
    // =====================================================================

    public function test_it_filters_by_area_when_valid_barangay(): void
    {
        $hit  = Listing::factory()->create(['is_verified' => true, 'barangay' => 'carmen']);
        $miss = Listing::factory()->create(['is_verified' => true, 'barangay' => 'lapasan']);

        $uuids = collect($this->getJson($this->endpoint(['area' => 'carmen']))->json('data'))->pluck('uuid');

        $this->assertContains($hit->uuid,    $uuids);
        $this->assertNotContains($miss->uuid, $uuids);
    }

    public function test_it_filters_by_type_csv_with_multiple_values(): void
    {
        $apt    = Listing::factory()->create(['is_verified' => true, 'type' => 'apartment']);
        $studio = Listing::factory()->create(['is_verified' => true, 'type' => 'studio']);
        $house  = Listing::factory()->create(['is_verified' => true, 'type' => 'house']);

        $uuids = collect($this->getJson($this->endpoint(['type' => 'apartment,studio']))->json('data'))->pluck('uuid');

        $this->assertContains($apt->uuid,      $uuids);
        $this->assertContains($studio->uuid,   $uuids);
        $this->assertNotContains($house->uuid, $uuids);
    }

    public function test_it_filters_by_budget_min_and_max_inclusive(): void
    {
        $below  = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 5000]);
        $inLow  = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 10000]);
        $inMid  = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 15000]);
        $inHigh = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 20000]);
        $above  = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 25000]);

        $uuids = collect(
            $this->getJson($this->endpoint(['budget_min' => '10000', 'budget_max' => '20000']))->json('data')
        )->pluck('uuid');

        $this->assertNotContains($below->uuid, $uuids);
        $this->assertContains($inLow->uuid,    $uuids);
        $this->assertContains($inMid->uuid,    $uuids);
        $this->assertContains($inHigh->uuid,   $uuids);
        $this->assertNotContains($above->uuid, $uuids);
    }

    public function test_it_filters_by_budget_min_only_and_echoes_max_as_null(): void
    {
        $below   = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 5000]);
        $atFloor = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 15000]);
        $above   = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 25000]);

        $response = $this->getJson($this->endpoint(['budget_min' => '15000']));
        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertNotContains($below->uuid,   $uuids);
        $this->assertContains($atFloor->uuid,    $uuids);
        $this->assertContains($above->uuid,      $uuids);

        $this->assertSame(15000, $response->json('filters.budget_min'));
        $this->assertNull($response->json('filters.budget_max'));
    }

    public function test_it_filters_by_budget_max_only_and_echoes_min_as_null(): void
    {
        $below = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 5000]);
        $atCap = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 15000]);
        $above = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 25000]);

        $response = $this->getJson($this->endpoint(['budget_max' => '15000']));
        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertContains($below->uuid,    $uuids);
        $this->assertContains($atCap->uuid,    $uuids);
        $this->assertNotContains($above->uuid, $uuids);

        $this->assertNull($response->json('filters.budget_min'));
        $this->assertSame(15000, $response->json('filters.budget_max'));
    }

    // =====================================================================
    // Filter normalization
    // =====================================================================

    public function test_it_swaps_budget_when_min_exceeds_max(): void
    {
        $cheap = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 8000]);
        $mid   = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 15000]);
        $pricy = Listing::factory()->create(['is_verified' => true, 'price_monthly' => 25000]);

        $response = $this->getJson($this->endpoint(['budget_min' => '20000', 'budget_max' => '10000']));
        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertNotContains($cheap->uuid, $uuids);
        $this->assertContains($mid->uuid,      $uuids);
        $this->assertNotContains($pricy->uuid, $uuids);

        $this->assertSame(10000, $response->json('filters.budget_min'));
        $this->assertSame(20000, $response->json('filters.budget_max'));
    }

    public function test_it_drops_invalid_filters_silently(): void
    {
        $response = $this->getJson($this->endpoint([
            'area'       => 'mordor',
            'type'       => 'fakeville,apartment,nullbarangay',
            'budget_min' => 'abc',
            'budget_max' => '-500',
        ]));

        $response->assertOk();
        $this->assertNull($response->json('filters.area'));
        $this->assertSame(['apartment'], $response->json('filters.type'));
        $this->assertNull($response->json('filters.budget_min'));
        $this->assertNull($response->json('filters.budget_max'));
    }

    // =====================================================================
    // Response shape
    // =====================================================================

    public function test_it_returns_data_links_meta_filters_and_catalogs_envelope(): void
    {
        Listing::factory()->create(['is_verified' => true]);

        $response = $this->getJson($this->endpoint())->assertOk();

        $keys = array_keys($response->json());
        sort($keys);

        $this->assertSame(['catalogs', 'data', 'filters', 'links', 'meta'], $keys);
        $this->assertIsArray($response->json('data'));
        $this->assertIsArray($response->json('links'));
        $this->assertIsArray($response->json('meta'));
    }

    public function test_listing_rows_use_card_resource_keys(): void
    {
        Listing::factory()->create(['is_verified' => true]);

        $row = $this->getJson($this->endpoint())->json('data.0');
        $keys = array_keys($row);
        sort($keys);

        $this->assertSame([
            'barangay', 'barangay_label', 'baths', 'beds',
            'id', 'image', 'image_count', 'price_monthly',
            'section', 'sqm', 'title', 'type', 'type_label', 'uuid',
        ], $keys);
    }

    public function test_filters_block_echoes_normalized_applied_values(): void
    {
        Listing::factory()->create(['is_verified' => true]);

        $response = $this->getJson($this->endpoint([
            'q'          => '  studio  ',
            'area'       => 'carmen',
            'type'       => 'apartment,studio',
            'budget_min' => '10000',
            'budget_max' => '20000',
        ]));

        $this->assertSame('studio',                  $response->json('filters.q'));
        $this->assertSame('carmen',                  $response->json('filters.area'));
        $this->assertSame(['apartment', 'studio'],   $response->json('filters.type'));
        $this->assertSame(10000,                     $response->json('filters.budget_min'));
        $this->assertSame(20000,                     $response->json('filters.budget_max'));
    }

    public function test_catalogs_returns_listing_types_and_barangays(): void
    {
        $response = $this->getJson($this->endpoint())->assertOk();

        $types     = $response->json('catalogs.listing_types');
        $barangays = $response->json('catalogs.barangays');

        $this->assertCount(count(ListingType::toValues()), $types);
        $this->assertCount(count(Barangay::toValues()),    $barangays);
        $this->assertSame(['label', 'value'], collect(array_keys($types[0]))->sort()->values()->all());
        $this->assertSame(['label', 'value'], collect(array_keys($barangays[0]))->sort()->values()->all());
    }

    // =====================================================================
    // Throttle — dual bucket (minute + hour), IP-keyed
    // =====================================================================

    public function test_it_throttles_when_minute_bucket_hits_cap(): void
    {
        // Pre-fill the minute bucket to its 60-request cap.
        // Tests run from 127.0.0.1 by default.
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($this->throttleKey('api-search', 'minute:127.0.0.1'), 60);
        }

        $this->getJson($this->endpoint())->assertStatus(429);
    }

    public function test_it_throttles_when_hour_bucket_hits_cap(): void
    {
        // Pre-fill the hour bucket to its 600-request cap.
        for ($i = 0; $i < 600; $i++) {
            RateLimiter::hit($this->throttleKey('api-search', 'hour:127.0.0.1'), 3600);
        }

        $this->getJson($this->endpoint())->assertStatus(429);
    }

    public function test_it_does_not_throttle_just_below_caps(): void
    {
        // 59 minute hits + 599 hour hits — both buckets just below cap. Request
        // succeeds; this pins that the caps are STRICT (>=, not >) on the
        // ThrottleRequests side.
        for ($i = 0; $i < 59; $i++) {
            RateLimiter::hit($this->throttleKey('api-search', 'minute:127.0.0.1'), 60);
        }
        for ($i = 0; $i < 599; $i++) {
            RateLimiter::hit($this->throttleKey('api-search', 'hour:127.0.0.1'), 3600);
        }

        $this->getJson($this->endpoint())->assertOk();
    }

    // =====================================================================
    // Middleware
    // =====================================================================

    public function test_it_applies_api_search_throttle_middleware(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('api.v1.search')
            ->gatherMiddleware();

        $this->assertContains(
            'throttle:api-search',
            $middleware,
            'GET /api/v1/search must use the api-search throttle (60/min + 600/hr by IP).',
        );
    }
}
