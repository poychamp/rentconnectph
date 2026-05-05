<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingRequestVerificationViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function readyListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()
            ->withImages(2)
            ->create(array_merge([
                'is_verified'    => false,
                'verified_at'    => null,
                'queue_status'   => QueueStatus::assigned()->value,
                'assigned_to'    => $officer->id,
                'assigned_at'    => Carbon::parse('2026-04-29 10:00:00'),
                'title'          => 'Test review listing',
                'directions'     => 'Past the green gate.',
                'latitude'       => 8.4831,
                'longitude'      => 124.6505,
            ], $overrides))
            ->fresh(['images']);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->readyListingFor($marco);

        $this->get(route('field.listings.show-request-verification', $listing->uuid))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->readyListingFor($marco);

        $this->get(route('field.listings.show-request-verification', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_when_listing_is_assigned_to_a_different_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->readyListingFor($carlo);

        $this->get(route('field.listings.show-request-verification', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_queue_status_is_not_assigned(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco, [
            'queue_status' => QueueStatus::unassigned()->value,
        ]);

        $this->get(route('field.listings.show-request-verification', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_listing_payload_with_expected_resource_shape(): void
    {
        $marco = $this->asMarco();

        $amenity = Amenity::firstOrCreate(['slug' => 'wifi'], [
            'name' => 'Wi-Fi',
            'icon' => 'wifi',
            'sort_order' => 1,
        ]);

        $listing = $this->readyListingFor($marco, [
            'title'              => 'Apartment near Capitol University',
            'type'               => ListingType::apartment()->value,
            'barangay'           => Barangay::lapasan()->value,
            'price_monthly'      => 12500,
            'beds'               => 2,
            'baths'              => 1,
            'sqm'                => 38,
            'description'        => 'Quiet building.',
            'directions'         => 'Past the green gate.',
            'latitude'           => 8.4831,
            'longitude'          => 124.6505,
            'verification_notes' => 'Pre-visit notes.',
            'contact_phone'      => '+639171234567',
            'contact_type'       => ContactType::owner()->value,
            'source_site'        => SourceSite::olx()->value,
            'source_url'         => 'https://olx.ph/test',
        ]);
        $img = $listing->images->first();
        $listing->update(['display_image_id' => $img->id]);
        $listing->amenities()->sync([$amenity->id]);

        $response = $this->get(route('field.listings.show-request-verification', $listing->uuid));
        $response->assertOk();
        $payload = $response->viewData('listing');

        $this->assertSame($listing->uuid, $payload['uuid']);
        $this->assertSame('Apartment near Capitol University', $payload['title']);
        $this->assertSame(ListingType::apartment()->label, $payload['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $payload['barangay_label']);
        $this->assertSame(12500, $payload['price_monthly']);
        $this->assertSame(2, $payload['beds']);
        $this->assertSame(1, $payload['baths']);
        $this->assertSame(38, $payload['sqm']);
        $this->assertSame('Quiet building.', $payload['description']);
        $this->assertSame('Past the green gate.', $payload['directions']);
        $this->assertSame('Pre-visit notes.', $payload['verification_notes']);
        $this->assertSame('+639171234567', $payload['contact_phone']);
        $this->assertSame(ContactType::owner()->label, $payload['contact_type_label']);
        $this->assertSame(SourceSite::olx()->label, $payload['source_site_label']);
        $this->assertSame('https://olx.ph/test', $payload['source_url']);
        $this->assertEqualsWithDelta(8.4831, (float) $payload['latitude'], 0.0001);
        $this->assertEqualsWithDelta(124.6505, (float) $payload['longitude'], 0.0001);

        $this->assertCount(2, $payload['images']);
        $this->assertSame($img->url, $payload['display_image_url']);

        $this->assertCount(1, $payload['amenities']);
        $this->assertSame($amenity->id, $payload['amenities'][0]['id']);
        $this->assertSame($amenity->name, $payload['amenities'][0]['name']);

        $this->assertNotNull($payload['mapbox_static_url']);
        $this->assertArrayHasKey('assigned_at', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertTrue($payload['prereqs']['has_title']);
        $this->assertTrue($payload['prereqs']['has_directions']);
        $this->assertTrue($payload['prereqs']['has_lat_lng']);
        $this->assertTrue($payload['prereqs']['has_at_least_one_photo']);
    }

    public function test_it_marks_has_lat_lng_false_when_either_coordinate_is_null(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco, [
            'latitude'  => null,
            'longitude' => null,
        ]);

        $response = $this->get(route('field.listings.show-request-verification', $listing->uuid));
        $payload = $response->viewData('listing');

        $this->assertFalse($payload['prereqs']['has_lat_lng']);
        $this->assertNull($payload['mapbox_static_url']);
    }

    public function test_it_marks_has_at_least_one_photo_false_when_listing_has_no_photos(): void
    {
        $marco = $this->asMarco();
        $listing = Listing::factory()->create([
            'is_verified'  => false,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to'  => $marco->id,
            'title'        => 'Apartment',
            'directions'   => 'Past the gate.',
            'latitude'     => 8.4831,
            'longitude'    => 124.6505,
        ]);

        $response = $this->get(route('field.listings.show-request-verification', $listing->uuid));
        $payload = $response->viewData('listing');

        $this->assertFalse($payload['prereqs']['has_at_least_one_photo']);
    }

    public function test_it_does_not_expose_field_priority_order_in_the_resource(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);
        $listing->update(['field_priority_order' => 7]);

        $response = $this->get(route('field.listings.show-request-verification', $listing->uuid));
        $payload = $response->viewData('listing');

        $this->assertArrayNotHasKey('field_priority_order', $payload);
    }
}
