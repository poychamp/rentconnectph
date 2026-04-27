<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingEditTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = Listing::factory()->withImages(2)->create();

        $response = $this->get(route('admin.listings.edit', $listing->uuid));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_the_edit_page_with_listing_pre_populated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $amenityA = Amenity::factory()->create();
        $amenityB = Amenity::factory()->create();

        $listing = Listing::factory()
            ->withImages(3)
            ->state([
                'title'         => 'Cozy 2-BR Apartment in Carmen',
                'description'   => 'Lovely place near the park.',
                'type'          => 'apartment',
                'price_monthly' => 18500,
                'barangay'      => 'carmen',
                'beds'          => 2,
                'baths'         => 1,
                'sqft'          => 65,
                'latitude'      => 8.4542123,    // full decimal(10,7) precision
                'longitude'     => 124.6411567,  // full decimal(10,7) precision
                'is_verified'   => true,
                'is_featured'   => false,
            ])
            ->create();
        $listing->amenities()->sync([$amenityA->id, $amenityB->id]);

        $response = $this->get(route('admin.listings.edit', $listing->uuid));

        $response->assertOk();
        $response->assertViewIs('admin.listings.edit');
        $response->assertViewHas('listing', fn ($viewListing) => $viewListing->id === $listing->id);
        $response->assertViewHas('listingTypes');
        $response->assertViewHas('barangays');
        $response->assertViewHas('amenities');

        $viewListing = $response->viewData('listing');
        $this->assertSame('Cozy 2-BR Apartment in Carmen', $viewListing->title);
        $this->assertSame('Lovely place near the park.', $viewListing->description);
        $this->assertSame('apartment', $viewListing->type);
        $this->assertSame(18500, $viewListing->price_monthly);
        $this->assertSame('carmen', $viewListing->barangay);
        $this->assertSame(2, $viewListing->beds);
        $this->assertSame(1, $viewListing->baths);
        $this->assertSame(65, $viewListing->sqft);

        // Lat/lng must be float (not stringified) and survive decimal(10,7) round-trip.
        $this->assertIsFloat($viewListing->latitude);
        $this->assertIsFloat($viewListing->longitude);
        $this->assertEqualsWithDelta(8.4542123,   $viewListing->latitude,  0.0000001);
        $this->assertEqualsWithDelta(124.6411567, $viewListing->longitude, 0.0000001);

        $this->assertTrue($viewListing->is_verified);
        $this->assertFalse($viewListing->is_featured);

        $this->assertCount(3, $viewListing->images);
        $this->assertSame([0, 1, 2], $viewListing->images->pluck('sort_order')->all());

        $attachedAmenities = $viewListing->amenities->pluck('id')->sort()->values()->all();
        $expected = collect([$amenityA->id, $amenityB->id])->sort()->values()->all();
        $this->assertSame($expected, $attachedAmenities);
    }

    public function test_it_returns_404_for_unknown_or_soft_deleted_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Unknown UUID
        $this->get(route('admin.listings.edit', '019dc5d9-ff77-7381-9755-000000000000'))
            ->assertNotFound();

        // Soft-deleted UUID
        $listing = Listing::factory()->create();
        $listing->delete();
        $this->get(route('admin.listings.edit', $listing->uuid))
            ->assertNotFound();
    }
}
