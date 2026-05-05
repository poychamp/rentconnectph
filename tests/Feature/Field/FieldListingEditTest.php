<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingEditTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asMarco(): User
    {
        $marco = User::factory()->field()->create();
        $this->actingAs($marco, 'admin');
        return $marco;
    }

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified' => false,
            'verified_at' => null,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to' => $officer->id,
        ], $overrides));
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        // No-role user — authed admin guard but no `field` role grant.
        // Super-admin can't be used here (Gate::before bypass).
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertForbidden();
    }

    public function test_it_returns_404_when_listing_is_assigned_to_a_different_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->assignedListingFor($carlo);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertNotFound();
    }

    public function test_it_returns_404_when_queue_status_is_not_assigned(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'queue_status' => QueueStatus::unassigned()->value,
        ]);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertNotFound();
    }

    public function test_it_returns_listing_payload_with_expected_resource_shape(): void
    {
        $marco = $this->asMarco();

        $amenity = Amenity::firstOrCreate(['slug' => 'wifi'], [
            'name' => 'Wi-Fi',
            'icon' => 'wifi',
            'sort_order' => 1,
        ]);

        $listing = $this->assignedListingFor($marco, [
            'title'         => 'Test Detail Listing',
            'type'          => ListingType::apartment()->value,
            'barangay'      => Barangay::lapasan()->value,
            'price_monthly' => 12500,
            'beds'          => 2,
            'baths'         => 1,
            'sqm'           => 38,
            'description'   => 'Quiet building, near Capitol University.',
            'directions'    => 'Look for the green gate beside the sari-sari store.',
            'contact_phone' => '+639171234567',
            'contact_type'  => ContactType::owner()->value,
            'source_site'   => SourceSite::olx()->value,
            'source_url'    => 'https://olx.ph/test-detail',
        ]);

        $img = ListingImage::create([
            'listing_id' => $listing->id,
            'url'        => 'https://example.com/photo.jpg',
            'sort_order' => 0,
        ]);
        $listing->update(['display_image_id' => $img->id]);
        $listing->amenities()->sync([$amenity->id]);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertOk();
        $payload = $response->viewData('listing');

        $this->assertSame($listing->uuid, $payload['uuid']);
        $this->assertSame('Test Detail Listing', $payload['title']);
        $this->assertSame(ListingType::apartment()->label, $payload['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $payload['barangay_label']);
        $this->assertSame(12500, $payload['price_monthly']);
        $this->assertSame(2, $payload['beds']);
        $this->assertSame(1, $payload['baths']);
        $this->assertSame(38, $payload['sqm']);
        $this->assertSame('Quiet building, near Capitol University.', $payload['description']);
        $this->assertSame('Look for the green gate beside the sari-sari store.', $payload['directions']);
        $this->assertSame('+639171234567', $payload['contact_phone']);
        $this->assertSame(ContactType::owner()->label, $payload['contact_type_label']);
        $this->assertSame(SourceSite::olx()->label, $payload['source_site_label']);
        $this->assertSame('https://olx.ph/test-detail', $payload['source_url']);

        $this->assertCount(1, $payload['images']);
        $this->assertSame($img->id, $payload['images'][0]['id']);
        $this->assertSame('https://example.com/photo.jpg', $payload['images'][0]['url']);

        $this->assertSame('https://example.com/photo.jpg', $payload['display_image_url']);

        $this->assertCount(1, $payload['amenities']);
        $this->assertSame($amenity->id, $payload['amenities'][0]['id']);
        $this->assertSame($amenity->name, $payload['amenities'][0]['name']);

        $this->assertArrayHasKey('assigned_at', $payload);
        $this->assertArrayHasKey('created_at', $payload);
    }

    public function test_it_does_not_expose_field_priority_order_in_the_resource(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);
        $listing->update(['field_priority_order' => 7]);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertOk();
        $payload = $response->viewData('listing');

        $this->assertArrayNotHasKey('field_priority_order', $payload);
    }

    public function test_it_returns_assigned_at_from_the_real_column_not_updated_at_proxy(): void
    {
        $marco = $this->asMarco();
        $assignedAt = Carbon::parse('2026-04-20 10:00:00');
        $listing = $this->assignedListingFor($marco, ['assigned_at' => $assignedAt]);

        $response = $this->get(route('field.listings.edit', $listing->uuid));

        $response->assertOk();
        $payload = $response->viewData('listing');

        $this->assertSame($assignedAt->toIso8601String(), $payload['assigned_at']);
    }
}
