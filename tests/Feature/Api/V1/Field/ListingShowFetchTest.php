<?php

namespace Tests\Feature\Api\V1\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\PrequalStatus;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingShowFetchTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function makeFieldUser(): User
    {
        return User::factory()->field()->create();
    }

    private function issueFieldToken(User $user): string
    {
        return $user->createToken('field-android', ['field'])->plainTextToken;
    }

    private function actAsFieldOfficer(): User
    {
        $user  = $this->makeFieldUser();
        $token = $this->issueFieldToken($user);
        $this->withHeaders(['Authorization' => "Bearer {$token}"]);
        return $user;
    }

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'is_verified'  => false,
            'verified_at'  => null,
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to'  => $officer->id,
        ], $overrides));
    }

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user    = $this->makeFieldUser();
        $token   = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;
        $listing = $this->assignedListingFor($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user    = User::factory()->create();
        $token   = $user->createToken('field-android', ['field'])->plainTextToken;
        $listing = Listing::factory()->create([
            'queue_status' => QueueStatus::assigned()->value,
            'assigned_to'  => $user->id,
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_for_nonexistent_uuid(): void
    {
        $this->actAsFieldOfficer();

        $this->getJson(route('api.v1.field.listings.show', (string) Str::uuid7()))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_assigned_to_a_different_officer(): void
    {
        $this->actAsFieldOfficer();
        $carlo  = User::factory()->field()->create();
        $carlos = $this->assignedListingFor($carlo);

        $this->getJson(route('api.v1.field.listings.show', $carlos->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_is_unassigned(): void
    {
        $this->actAsFieldOfficer();
        $unassigned = Listing::factory()->create([
            'is_verified'  => false,
            'queue_status' => QueueStatus::unassigned()->value,
            'assigned_to'  => null,
        ]);

        $this->getJson(route('api.v1.field.listings.show', $unassigned->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco);
        $listing->delete();

        $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_assigned_listing_owned_by_current_officer(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco);

        $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->assertJsonPath('listing.uuid', $listing->uuid);
    }

    public function test_it_returns_visited_listing_owned_by_current_officer(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco, [
            'queue_status' => QueueStatus::visited()->value,
            'visited_at'   => Carbon::parse('2026-04-25 10:00:00'),
        ]);

        $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->assertJsonPath('listing.uuid', $listing->uuid)
            ->assertJsonPath('listing.queue_status', QueueStatus::visited()->value)
            ->assertJsonPath('listing.queue_status_label', QueueStatus::visited()->label);
    }

    public function test_it_returns_resource_shape_with_raw_values_and_labels(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco, [
            'title'                => 'Test Listing',
            'type'                 => ListingType::apartment()->value,
            'barangay'             => Barangay::lapasan()->value,
            'price_monthly'        => 12000,
            'beds'                 => 2,
            'baths'                => 1,
            'sqm'                  => 35,
            'latitude'             => 8.4845,
            'longitude'            => 124.6526,
            'description'          => 'A nice place.',
            'directions'           => 'Near Gaisano City',
            'verification_notes'  => 'Visited 2026-04-20.',
            'contact_phone'        => '+639171234567',
            'contact_type'         => ContactType::owner()->value,
            'source_site'          => SourceSite::olx()->value,
            'source_url'           => 'https://olx.ph/test',
            'prequal_status'       => PrequalStatus::calledYes()->value,
            'is_field_priority'    => true,
            'field_priority_order' => 3,
            'queue_status'         => QueueStatus::assigned()->value,
            'is_verified'          => false,
            'verified_at'          => null,
            'assigned_at'          => Carbon::parse('2026-04-15 09:00:00'),
        ]);

        $row = $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->json('listing');

        // Identity + raw values (Android round-trips edits).
        $this->assertSame($listing->uuid, $row['uuid']);
        $this->assertSame('Test Listing', $row['title']);
        $this->assertSame(ListingType::apartment()->value, $row['type']);
        $this->assertSame(Barangay::lapasan()->value, $row['barangay']);
        $this->assertSame(ContactType::owner()->value, $row['contact_type']);
        $this->assertSame(SourceSite::olx()->value, $row['source_site']);
        $this->assertSame(PrequalStatus::calledYes()->value, $row['prequal_status']);

        // Labels (display).
        $this->assertSame(ListingType::apartment()->label, $row['type_label']);
        $this->assertSame(Barangay::lapasan()->label, $row['barangay_label']);
        $this->assertSame(ContactType::owner()->label, $row['contact_type_label']);
        $this->assertSame(SourceSite::olx()->label, $row['source_site_label']);
        $this->assertSame(PrequalStatus::calledYes()->label, $row['prequal_status_label']);

        // Specs.
        $this->assertSame(12000, $row['price_monthly']);
        $this->assertSame(2, $row['beds']);
        $this->assertSame(1, $row['baths']);
        $this->assertSame(35, $row['sqm']);
        $this->assertSame(8.4845, $row['latitude']);
        $this->assertSame(124.6526, $row['longitude']);

        // Long-form text.
        $this->assertSame('A nice place.', $row['description']);
        $this->assertSame('Near Gaisano City', $row['directions']);
        $this->assertSame('Visited 2026-04-20.', $row['verification_notes']);

        // Contact.
        $this->assertSame('+639171234567', $row['contact_phone']);
        $this->assertSame('https://olx.ph/test', $row['source_url']);

        // Lifecycle state (additive vs FieldListingResource — Android needs these).
        $this->assertSame(QueueStatus::assigned()->value, $row['queue_status']);
        $this->assertSame(QueueStatus::assigned()->label, $row['queue_status_label']);
        $this->assertFalse($row['is_verified']);
        $this->assertNull($row['verified_at']);
        $this->assertNull($row['visited_at']);
        $this->assertTrue($row['is_field_priority']);
        $this->assertSame(3, $row['field_priority_order']);
        $this->assertSame(
            Carbon::parse('2026-04-15 09:00:00')->toIso8601String(),
            $row['assigned_at']
        );
        $this->assertArrayHasKey('created_at', $row);
    }

    public function test_it_returns_images_ordered_by_sort_order(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco);

        $second = ListingImage::create([
            'listing_id' => $listing->id,
            'url'        => 'https://example.test/2.jpg',
            'sort_order' => 1,
        ]);
        $first = ListingImage::create([
            'listing_id' => $listing->id,
            'url'        => 'https://example.test/1.jpg',
            'sort_order' => 0,
        ]);
        $third = ListingImage::create([
            'listing_id' => $listing->id,
            'url'        => 'https://example.test/3.jpg',
            'sort_order' => 2,
        ]);

        $images = $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->json('listing.images');

        $this->assertCount(3, $images);
        $this->assertSame($first->id,  $images[0]['id']);
        $this->assertSame($second->id, $images[1]['id']);
        $this->assertSame($third->id,  $images[2]['id']);
        $this->assertSame('https://example.test/1.jpg', $images[0]['url']);
    }

    public function test_it_returns_amenities_attached_to_the_listing(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco);

        $wifi = Amenity::where('slug', 'wifi')->first()
            ?? Amenity::factory()->create(['slug' => 'wifi', 'name' => 'Wi-Fi', 'icon' => 'wifi']);
        $parking = Amenity::where('slug', 'parking')->first()
            ?? Amenity::factory()->create(['slug' => 'parking', 'name' => 'Parking', 'icon' => 'car']);
        $listing->amenities()->sync([$wifi->id, $parking->id]);

        $amenities = $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->json('listing.amenities');

        $this->assertCount(2, $amenities);

        // Each amenity row exposes id + name + slug + icon — Android renders pills
        // with icons (mirrors admin web's AdminAddListingAmenityPills.vue).
        foreach ($amenities as $a) {
            $this->assertArrayHasKey('id', $a);
            $this->assertArrayHasKey('name', $a);
            $this->assertArrayHasKey('slug', $a);
            $this->assertArrayHasKey('icon', $a);
        }

        $bySlug = collect($amenities)->keyBy('slug');
        $this->assertSame('Wi-Fi', $bySlug['wifi']['name']);
        $this->assertSame($wifi->icon, $bySlug['wifi']['icon']);
        $this->assertSame('Parking', $bySlug['parking']['name']);
        $this->assertSame($parking->icon, $bySlug['parking']['icon']);
    }

    public function test_it_returns_catalogs_for_dropdowns_and_amenity_picker(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->assignedListingFor($marco);

        // Inline amenity catalog — TestSeeder doesn't run AmenitySeeder, so we
        // pin the catalog with known rows.
        $wifi    = Amenity::factory()->create(['slug' => 'wifi',    'name' => 'WiFi',    'icon' => 'wifi',    'sort_order' => 1]);
        $parking = Amenity::factory()->create(['slug' => 'parking', 'name' => 'Parking', 'icon' => 'parking', 'sort_order' => 2]);
        $aircon  = Amenity::factory()->create(['slug' => 'aircon',  'name' => 'Aircon',  'icon' => 'aircon',  'sort_order' => 3]);

        $catalogs = $this->getJson(route('api.v1.field.listings.show', $listing->uuid))
            ->assertOk()
            ->json('catalogs');

        // Five top-level catalogs ship under `catalogs`: 4 enum dropdowns + amenities.
        $this->assertArrayHasKey('listing_types', $catalogs);
        $this->assertArrayHasKey('barangays', $catalogs);
        $this->assertArrayHasKey('source_sites', $catalogs);
        $this->assertArrayHasKey('contact_types', $catalogs);
        $this->assertArrayHasKey('amenities', $catalogs);

        // Enum catalogs: array of {value, label}, full coverage of toValues() in declaration order.
        $this->assertSame(
            ListingType::toValues(),
            collect($catalogs['listing_types'])->pluck('value')->all(),
        );
        $this->assertContains(
            ['value' => ListingType::apartment()->value, 'label' => ListingType::apartment()->label],
            $catalogs['listing_types'],
        );

        $this->assertSame(
            Barangay::toValues(),
            collect($catalogs['barangays'])->pluck('value')->all(),
        );
        $this->assertContains(
            ['value' => Barangay::lapasan()->value, 'label' => Barangay::lapasan()->label],
            $catalogs['barangays'],
        );

        $this->assertSame(
            SourceSite::toValues(),
            collect($catalogs['source_sites'])->pluck('value')->all(),
        );
        $this->assertContains(
            ['value' => SourceSite::olx()->value, 'label' => SourceSite::olx()->label],
            $catalogs['source_sites'],
        );

        $this->assertSame(
            ContactType::toValues(),
            collect($catalogs['contact_types'])->pluck('value')->all(),
        );
        $this->assertContains(
            ['value' => ContactType::owner()->value, 'label' => ContactType::owner()->label],
            $catalogs['contact_types'],
        );

        // Amenity catalog: array of {id, name, slug, icon}, ordered by sort_order ASC.
        $this->assertCount(3, $catalogs['amenities']);
        foreach ($catalogs['amenities'] as $a) {
            $this->assertArrayHasKey('id', $a);
            $this->assertArrayHasKey('name', $a);
            $this->assertArrayHasKey('slug', $a);
            $this->assertArrayHasKey('icon', $a);
        }
        $orderedIds = collect($catalogs['amenities'])->pluck('id')->all();
        $this->assertSame([$wifi->id, $parking->id, $aircon->id], $orderedIds);
        $this->assertSame('WiFi',    $catalogs['amenities'][0]['name']);
        $this->assertSame('wifi',    $catalogs['amenities'][0]['slug']);
        $this->assertSame('parking', $catalogs['amenities'][1]['icon']);
    }

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.show');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
        $this->assertNotContains(\App\Http\Middleware\AllowsBfcache::class, $middleware);
    }
}
