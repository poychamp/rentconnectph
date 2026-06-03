<?php

namespace Tests\Feature\Api\V1\Public;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\ListingImage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class HomeFetchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function featuredListing(int $order, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_featured'    => true,
            'featured_order' => $order,
            'is_verified'    => true,
            'verified_at'    => Carbon::now(),
            'listed_at'      => Carbon::now(),
        ], $overrides));
    }

    private function verifiedListing(string $listedAt, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
            'listed_at'   => Carbon::parse($listedAt),
            'is_featured' => false,
        ], $overrides));
    }

    // ===========================================================
    // Auth (public endpoint)
    // ===========================================================

    public function test_it_allows_guest_with_no_token(): void
    {
        $this->getJson(route('api.v1.home'))
            ->assertOk();
    }

    // ===========================================================
    // Featured slice
    // ===========================================================

    public function test_it_returns_featured_verified_listings_under_featured_key(): void
    {
        $a = $this->featuredListing(1);
        $b = $this->featuredListing(2);

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$a->uuid, $b->uuid],
            collect($response->json('featured'))->pluck('uuid')->all(),
        );
    }

    public function test_it_excludes_non_featured_verified_listings_from_featured_key(): void
    {
        $featured = $this->featuredListing(1);
        $this->verifiedListing('2026-05-01');

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$featured->uuid],
            collect($response->json('featured'))->pluck('uuid')->all(),
        );
    }

    public function test_it_excludes_featured_unverified_listings_from_featured_key(): void
    {
        $verifiedFeatured = $this->featuredListing(1);
        Listing::factory()->create([
            'is_featured'    => true,
            'featured_order' => 2,
            'is_verified'    => false,
            'verified_at'    => null,
            'listed_at'      => null,
        ]);

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$verifiedFeatured->uuid],
            collect($response->json('featured'))->pluck('uuid')->all(),
        );
    }

    public function test_it_excludes_soft_deleted_from_featured_key(): void
    {
        $alive = $this->featuredListing(1);
        $dead  = $this->featuredListing(2);
        $dead->delete();

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$alive->uuid],
            collect($response->json('featured'))->pluck('uuid')->all(),
        );
    }

    public function test_it_orders_featured_by_featured_order_asc(): void
    {
        $third  = $this->featuredListing(3);
        $first  = $this->featuredListing(1);
        $second = $this->featuredListing(2);

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$first->uuid, $second->uuid, $third->uuid],
            collect($response->json('featured'))->pluck('uuid')->all(),
        );
    }

    // ===========================================================
    // Recently slice
    // ===========================================================

    public function test_it_returns_verified_listings_under_recently_key(): void
    {
        $this->verifiedListing('2026-05-01');
        $this->verifiedListing('2026-05-02');

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertCount(2, $response->json('recently'));
    }

    public function test_it_excludes_unverified_from_recently_key(): void
    {
        $verified = $this->verifiedListing('2026-05-01');
        Listing::factory()->unverified()->create();

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$verified->uuid],
            collect($response->json('recently'))->pluck('uuid')->all(),
        );
    }

    public function test_it_excludes_soft_deleted_from_recently_key(): void
    {
        $alive = $this->verifiedListing('2026-05-02');
        $dead  = $this->verifiedListing('2026-05-01');
        $dead->delete();

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$alive->uuid],
            collect($response->json('recently'))->pluck('uuid')->all(),
        );
    }

    public function test_it_caps_recently_at_six_listings(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->verifiedListing("2026-05-{$day}");
        }

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertCount(6, $response->json('recently'));
    }

    public function test_it_orders_recently_by_listed_at_desc(): void
    {
        $oldest = $this->verifiedListing('2026-04-01');
        $middle = $this->verifiedListing('2026-04-15');
        $newest = $this->verifiedListing('2026-05-01');

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertEquals(
            [$newest->uuid, $middle->uuid, $oldest->uuid],
            collect($response->json('recently'))->pluck('uuid')->all(),
        );
    }

    // ===========================================================
    // Empty state
    // ===========================================================

    public function test_it_returns_empty_arrays_when_no_listings_exist(): void
    {
        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertSame([], $response->json('featured'));
        $this->assertSame([], $response->json('recently'));
        $this->assertNotEmpty($response->json('catalogs.listing_types'));
        $this->assertNotEmpty($response->json('catalogs.barangays'));
    }

    // ===========================================================
    // Catalogs
    // ===========================================================

    public function test_it_returns_listing_types_and_barangays_under_catalogs(): void
    {
        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertCount(
            count(ListingType::toValues()),
            $response->json('catalogs.listing_types'),
        );
        $this->assertCount(
            count(Barangay::toValues()),
            $response->json('catalogs.barangays'),
        );
    }

    public function test_catalog_row_has_value_and_label_keys(): void
    {
        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertSame(
            ['value', 'label'],
            array_keys($response->json('catalogs.listing_types.0')),
        );
        $this->assertSame(
            ['value', 'label'],
            array_keys($response->json('catalogs.barangays.0')),
        );
    }

    // ===========================================================
    // Response shape
    // ===========================================================

    public function test_response_has_top_level_featured_recently_catalogs_keys(): void
    {
        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertSame(
            ['featured', 'recently', 'catalogs'],
            array_keys($response->json()),
        );
    }

    public function test_listing_card_row_has_expected_keys(): void
    {
        $listing = $this->featuredListing(1);
        $img = ListingImage::factory()->create([
            'listing_id' => $listing->id,
            'sort_order' => 0,
        ]);
        $listing->update(['display_image_id' => $img->id]);

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertSame(
            [
                'id', 'uuid', 'title', 'type', 'type_label', 'price_monthly',
                'beds', 'baths', 'sqm', 'barangay', 'barangay_label',
                'image', 'image_count', 'section',
            ],
            array_keys($response->json('featured.0')),
        );
    }

    public function test_section_field_is_verified_for_featured_and_recently_for_recent(): void
    {
        $this->featuredListing(1);
        $r = $this->verifiedListing('2026-05-01');

        $response = $this->getJson(route('api.v1.home'))->assertOk();

        $this->assertSame('verified', $response->json('featured.0.section'));

        $recentRow = collect($response->json('recently'))
            ->firstWhere('uuid', $r->uuid);
        $this->assertSame('recently', $recentRow['section']);
    }

    // ===========================================================
    // Middleware
    // ===========================================================

    public function test_it_lives_in_v1_group_with_no_auth_middleware(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.home');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertNotContains('auth:sanctum', $middleware);
        $this->assertNotContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
