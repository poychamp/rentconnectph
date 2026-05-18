<?php

namespace Tests\Feature\Api\V1\Public;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\ListingImage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingDetailFetchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function endpoint(string $uuid): string
    {
        return "/api/v1/listings/{$uuid}";
    }

    private function verifiedListing(array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-15 10:00:00'),
            'listed_at'   => Carbon::parse('2026-04-12 09:30:00'),
        ], $overrides));
    }

    // =====================================================================
    // Auth
    // =====================================================================

    public function test_it_allows_guest_with_no_token(): void
    {
        $listing = $this->verifiedListing();

        $this->getJson($this->endpoint($listing->uuid))
            ->assertOk();
    }

    // =====================================================================
    // 404 paths
    // =====================================================================

    public function test_it_returns_404_when_listing_is_unverified(): void
    {
        $listing = Listing::factory()->create(['is_verified' => false]);

        $this->getJson($this->endpoint($listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_is_soft_deleted(): void
    {
        $listing = $this->verifiedListing();
        $listing->delete();

        $this->getJson($this->endpoint($listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_uuid_does_not_exist(): void
    {
        $this->getJson($this->endpoint('019dbeef-0000-7000-8000-000000000000'))
            ->assertNotFound();
    }

    // =====================================================================
    // Response shape
    // =====================================================================

    public function test_it_returns_named_key_envelope_with_listing_object(): void
    {
        $listing = $this->verifiedListing();

        $response = $this->getJson($this->endpoint($listing->uuid))->assertOk();

        $this->assertSame(['listing'], array_keys($response->json()));
        $this->assertIsArray($response->json('listing'));
    }

    public function test_it_returns_listing_with_expected_top_level_fields(): void
    {
        $listing = $this->verifiedListing();

        $keys = array_keys(
            $this->getJson($this->endpoint($listing->uuid))->json('listing')
        );
        sort($keys);

        $expected = [
            'amenities', 'barangay', 'barangay_label', 'baths', 'beds',
            'description', 'id', 'images', 'latitude', 'listed_at',
            'longitude', 'price_monthly', 'sqm', 'title', 'type',
            'type_label', 'uuid', 'verified_at',
        ];

        $this->assertSame($expected, $keys);
    }

    public function test_it_returns_pre_resolved_type_and_barangay_labels(): void
    {
        $listing = $this->verifiedListing([
            'type'     => 'apartment',
            'barangay' => 'carmen',
        ]);

        $payload = $this->getJson($this->endpoint($listing->uuid))->json('listing');

        $this->assertSame('apartment', $payload['type']);
        $this->assertSame('Apartment', $payload['type_label']);
        $this->assertSame('carmen', $payload['barangay']);
        $this->assertSame('Carmen', $payload['barangay_label']);
    }

    public function test_it_returns_timestamps_with_manila_offset(): void
    {
        $listing = $this->verifiedListing([
            'verified_at' => Carbon::parse('2026-04-15 10:00:00'),
            'listed_at'   => Carbon::parse('2026-04-12 09:30:00'),
        ]);

        $payload = $this->getJson($this->endpoint($listing->uuid))->json('listing');

        $this->assertSame('2026-04-15T10:00:00+08:00', $payload['verified_at']);
        $this->assertSame('2026-04-12T09:30:00+08:00', $payload['listed_at']);
    }

    public function test_it_returns_images_with_expected_keys(): void
    {
        $listing = $this->verifiedListing();
        ListingImage::factory()->for($listing)->create(['sort_order' => 1]);
        ListingImage::factory()->for($listing)->create(['sort_order' => 2]);

        $images = $this->getJson($this->endpoint($listing->uuid))->json('listing.images');

        $this->assertCount(2, $images);
        foreach ($images as $img) {
            $keys = array_keys($img);
            sort($keys);
            $this->assertSame(['id', 'sort_order', 'url', 'uuid'], $keys);
        }
    }

    public function test_it_returns_amenities_with_expected_keys(): void
    {
        $listing = $this->verifiedListing();
        $amenity = Amenity::factory()->create();
        $listing->amenities()->attach($amenity);

        $amenities = $this->getJson($this->endpoint($listing->uuid))->json('listing.amenities');

        $this->assertCount(1, $amenities);
        $keys = array_keys($amenities[0]);
        sort($keys);
        $this->assertSame(['icon', 'id', 'name', 'slug', 'uuid'], $keys);
    }

    // =====================================================================
    // Drift-pins (deliberate omissions vs web ListingDetailResource + privacy)
    // =====================================================================

    public function test_it_excludes_map_url_from_response(): void
    {
        $listing = $this->verifiedListing();

        $payload = $this->getJson($this->endpoint($listing->uuid))->json('listing');

        $this->assertArrayNotHasKey('map_url', $payload);
    }

    public function test_it_excludes_contact_phone_for_renter_privacy(): void
    {
        $contact = ListingContact::factory()->create(['phone' => '+639171234567']);
        $listing = $this->verifiedListing(['listing_contact_id' => $contact->id]);

        $payload = $this->getJson($this->endpoint($listing->uuid))->json('listing');

        $this->assertArrayNotHasKey('contact_phone', $payload);
        $this->assertArrayNotHasKey('listing_contact', $payload);
        $this->assertStringNotContainsString('+639171234567', json_encode($payload));
    }
}
