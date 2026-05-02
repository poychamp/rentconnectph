<?php

namespace Tests\Feature\Admin;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\SourceSite;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingEditViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    private function verifiedListing(array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-30 16:00:00'),
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->verifiedListing();

        $this->get(route('admin.listings.edit', $listing->uuid))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_listings_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $listing = $this->verifiedListing();

        $this->get(route('admin.listings.edit', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $this->asAdmin();

        $this->get(route('admin.listings.edit', '019dc5d9-ff77-7381-9755-000000000000'))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $this->asAdmin();

        $listing = $this->verifiedListing();
        $listing->delete();

        $this->get(route('admin.listings.edit', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_is_not_verified(): void
    {
        $this->asAdmin();

        $unverified = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $this->get(route('admin.listings.edit', $unverified->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_listing_payload_with_expected_shape(): void
    {
        $this->asAdmin();

        $field = User::factory()->create(['name' => 'Marco Reyes']);

        $listing = $this->verifiedListing([
            'title'              => 'Apartment near Capitol',
            'description'        => 'Quiet street, 5 min walk to Capitol.',
            'type'               => ListingType::apartment()->value,
            'price_monthly'      => 12500,
            'barangay'           => Barangay::lapasan()->value,
            'beds'               => 2,
            'baths'              => 1,
            'sqm'                => 38,
            'latitude'           => 8.4831,
            'longitude'          => 124.6505,
            'directions'         => 'Past the green gate.',
            'contact_phone'      => '+639171234567',
            'contact_type'       => ContactType::owner()->value,
            'source_site'        => SourceSite::rentPh()->value,
            'source_url'         => 'https://rent.ph/property/example',
            'verification_notes' => 'Owner Josie spoke clearly; ready for visit.',
            'assigned_to'        => $field->id,
            'visited_at'         => Carbon::parse('2026-04-30 14:00:00'),
            'is_featured'        => false,
        ]);

        $img1 = ListingImage::create(['listing_id' => $listing->id, 'url' => 'https://cdn.test/a.jpg', 'sort_order' => 1]);
        $img0 = ListingImage::create(['listing_id' => $listing->id, 'url' => 'https://cdn.test/b.jpg', 'sort_order' => 0]);

        $amenity = Amenity::first()
            ?? Amenity::create(['name' => 'WiFi', 'slug' => 'wifi', 'icon' => 'wifi', 'sort_order' => 1]);
        $listing->amenities()->attach($amenity->id);

        $response = $this->get(route('admin.listings.edit', $listing->uuid));
        $response->assertOk();

        $payload = $response->viewData('listing');

        // Array, not Model — clean Blade injection.
        $this->assertIsArray($payload);

        // Scalars
        $this->assertSame($listing->id,   $payload['id']);
        $this->assertSame($listing->uuid, $payload['uuid']);
        $this->assertSame('Apartment near Capitol', $payload['title']);
        $this->assertSame('Quiet street, 5 min walk to Capitol.', $payload['description']);
        $this->assertSame(ListingType::apartment()->value, $payload['type']);
        $this->assertSame(12500, $payload['price_monthly']);
        $this->assertSame(Barangay::lapasan()->value, $payload['barangay']);
        $this->assertSame(2, $payload['beds']);
        $this->assertSame(1, $payload['baths']);
        $this->assertSame(38, $payload['sqm']);
        $this->assertEqualsWithDelta(8.4831,   $payload['latitude'],  0.0001);
        $this->assertEqualsWithDelta(124.6505, $payload['longitude'], 0.0001);
        $this->assertSame('Past the green gate.', $payload['directions']);

        // Calls-team-context — present on the verified Edit payload (parity with verify-edit)
        $this->assertSame('+639171234567',                  $payload['contact_phone']);
        $this->assertSame(ContactType::owner()->value,      $payload['contact_type']);
        $this->assertSame(SourceSite::rentPh()->value,      $payload['source_site']);
        $this->assertSame('https://rent.ph/property/example', $payload['source_url']);
        $this->assertSame('Owner Josie spoke clearly; ready for visit.', $payload['verification_notes']);

        // Field-officer-context — read-only display
        $this->assertSame('Marco Reyes', $payload['assigned_to_name']);
        $this->assertNotNull($payload['visited_at']);

        // Moderation toggle
        $this->assertArrayHasKey('is_featured', $payload);
        $this->assertFalse($payload['is_featured']);

        // Children — images sorted ASC
        $this->assertCount(2, $payload['images']);
        $this->assertSame(0, $payload['images'][0]['sort_order']);
        $this->assertSame('https://cdn.test/b.jpg', $payload['images'][0]['url']);
        $this->assertSame(1, $payload['images'][1]['sort_order']);
        $this->assertSame('https://cdn.test/a.jpg', $payload['images'][1]['url']);

        // Children — amenities present with full shape
        $this->assertCount(1, $payload['amenities']);
        $this->assertSame($amenity->id, $payload['amenities'][0]['id']);
        $this->assertArrayHasKey('name', $payload['amenities'][0]);
        $this->assertArrayHasKey('slug', $payload['amenities'][0]);
        $this->assertArrayHasKey('icon', $payload['amenities'][0]);
    }

    public function test_it_returns_lookup_arrays_for_selects(): void
    {
        $this->asAdmin();

        $listing = $this->verifiedListing();

        $response = $this->get(route('admin.listings.edit', $listing->uuid));
        $response->assertOk();

        $listingTypes = $response->viewData('listingTypes');
        $this->assertIsArray($listingTypes);
        $this->assertNotEmpty($listingTypes);
        $this->assertArrayHasKey('value', $listingTypes[0]);
        $this->assertArrayHasKey('label', $listingTypes[0]);
        $this->assertSame(count(ListingType::toValues()), count($listingTypes));

        $barangays = $response->viewData('barangays');
        $this->assertIsArray($barangays);
        $this->assertSame(count(Barangay::toValues()), count($barangays));

        $sourceSites = $response->viewData('sourceSites');
        $this->assertIsArray($sourceSites);
        $this->assertSame(count(SourceSite::toValues()), count($sourceSites));

        $contactTypes = $response->viewData('contactTypes');
        $this->assertIsArray($contactTypes);
        $this->assertSame(count(ContactType::toValues()), count($contactTypes));

        $amenities = $response->viewData('amenities');
        $this->assertNotNull($amenities);
        if ($amenities->isNotEmpty()) {
            $first = $amenities->first();
            $this->assertNotNull($first->id);
            $this->assertNotNull($first->name);
            $this->assertNotNull($first->slug);
        }
    }
}
