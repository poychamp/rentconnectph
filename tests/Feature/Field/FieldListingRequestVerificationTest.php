<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingRequestVerificationTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

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
                'title'          => 'Apartment near Capitol University',
                'directions'     => 'Past the green gate.',
                'latitude'       => 8.4831,
                'longitude'      => 124.6505,
            ], $overrides))
            ->fresh(['images']);
    }

    private function seedTmpPhoto(string $name): array
    {
        Storage::disk('s3')->put("tmp/{$name}", 'fake-image-bytes');
        return ['name' => "{$name}.jpg", 'size' => 12345, 'key' => "tmp/{$name}"];
    }

    private function existingPhotos(Listing $listing): array
    {
        return $listing->images
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($img) => ['existing_id' => $img->id])
            ->all();
    }

    private function validPayload(Listing $listing, array $overrides = []): array
    {
        $tmpPhoto = $this->seedTmpPhoto('new-' . bin2hex(random_bytes(4)) . '.jpg');

        return array_merge([
            'title'         => $listing->title,
            'description'   => $listing->description ?? 'Description.',
            'listing_type'  => ListingType::apartment()->value,
            'price_monthly' => $listing->price_monthly ?? 12500,
            'barangay'      => Barangay::lapasan()->value,
            'beds'          => $listing->beds ?? 2,
            'baths'         => $listing->baths ?? 1,
            'sqm'           => $listing->sqm ?? 38,
            'latitude'      => $listing->latitude,
            'longitude'     => $listing->longitude,
            'directions'    => $listing->directions,
            'amenities'     => [],
            'photos'        => array_merge(
                $this->existingPhotos($listing),
                [$tmpPhoto], // at least one new photo
            ),
        ], $overrides);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->readyListingFor($marco);

        $this->put(route('field.listings.request-verification', $listing->uuid))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->readyListingFor($marco);

        $this->put(route('field.listings.request-verification', $listing->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_422_when_listing_is_assigned_to_a_different_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->readyListingFor($carlo);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        )->assertSessionHasErrors(['listing'], null, 'requestVerification');
    }

    public function test_it_returns_422_when_queue_status_is_not_assigned(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco, [
            'queue_status' => QueueStatus::unassigned()->value,
        ]);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        )->assertSessionHasErrors(['listing'], null, 'requestVerification');
    }

    public function test_it_requires_title(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['title' => '']),
        )->assertSessionHasErrors(['title'], null, 'requestVerification');
    }

    public function test_it_requires_listing_type(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['listing_type' => '']),
        )->assertSessionHasErrors(['listing_type'], null, 'requestVerification');
    }

    public function test_it_requires_barangay(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['barangay' => '']),
        )->assertSessionHasErrors(['barangay'], null, 'requestVerification');
    }

    public function test_it_requires_directions(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['directions' => '']),
        )->assertSessionHasErrors(['directions'], null, 'requestVerification');
    }

    public function test_it_requires_latitude_and_longitude(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['latitude' => null, 'longitude' => null]),
        )->assertSessionHasErrors(['latitude', 'longitude'], null, 'requestVerification');
    }

    public function test_it_requires_at_least_one_new_photo_uploaded_during_the_session(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        // Submit with only existing_id photos — no tmp/ uploads.
        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => $this->existingPhotos($listing),
            ]),
        )->assertSessionHasErrors(['photos'], null, 'requestVerification');
    }

    public function test_it_persists_editable_scalar_field_changes_on_submission(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco, [
            'title'         => 'Original Title',
            'description'   => 'Original description.',
            'type'          => ListingType::condo()->value,
            'price_monthly' => 9999,
            'barangay'      => Barangay::carmen()->value,
            'beds'          => 1,
            'baths'         => 1,
            'sqm'           => 25,
            'directions'    => 'Old directions.',
        ]);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'title'         => 'Updated by Field Officer',
                'description'   => 'Saw the front gate.',
                'listing_type'  => ListingType::apartment()->value,
                'price_monthly' => 12500,
                'barangay'      => Barangay::lapasan()->value,
                'beds'          => 2,
                'baths'         => 1,
                'sqm'           => 38,
                'latitude'      => 8.4831,
                'longitude'     => 124.6505,
                'directions'    => 'Past the green gate.',
            ]),
        );

        $listing->refresh();
        $this->assertSame('Updated by Field Officer', $listing->title);
        $this->assertSame('Saw the front gate.', $listing->description);
        $this->assertSame(ListingType::apartment()->value, $listing->type);
        $this->assertSame(12500, $listing->price_monthly);
        $this->assertSame(Barangay::lapasan()->value, $listing->barangay);
        $this->assertSame(2, $listing->beds);
        $this->assertSame(1, $listing->baths);
        $this->assertSame(38, $listing->sqm);
        $this->assertEqualsWithDelta(8.4831, (float) $listing->latitude, 0.0001);
        $this->assertEqualsWithDelta(124.6505, (float) $listing->longitude, 0.0001);
        $this->assertSame('Past the green gate.', $listing->directions);
    }

    public function test_it_deletes_photos_that_are_not_in_the_submitted_array(): void
    {
        $marco = $this->asMarco();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'    => false,
                'verified_at'    => null,
                'queue_status'   => QueueStatus::assigned()->value,
                'assigned_to'    => $marco->id,
                'assigned_at'    => Carbon::parse('2026-04-29 10:00:00'),
                'title'          => 'Apartment',
                'directions'     => 'Past the gate.',
                'latitude'       => 8.4831,
                'longitude'      => 124.6505,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $kept     = $existing[0];
        $removedA = $existing[1];
        $removedB = $existing[2];

        $tmpPhoto = $this->seedTmpPhoto('new-' . bin2hex(random_bytes(4)) . '.jpg');

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $kept->id],
                    $tmpPhoto,
                ],
            ]),
        );

        $listing->refresh()->load('images');

        $this->assertCount(2, $listing->images);
        $this->assertContains($kept->id, $listing->images->pluck('id')->all());
        $this->assertNull(\App\Models\ListingImage::find($removedA->id));
        $this->assertNull(\App\Models\ListingImage::find($removedB->id));
    }

    public function test_it_persists_photo_sort_order_matching_the_submitted_array_order(): void
    {
        $marco = $this->asMarco();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'    => false,
                'verified_at'    => null,
                'queue_status'   => QueueStatus::assigned()->value,
                'assigned_to'    => $marco->id,
                'assigned_at'    => Carbon::parse('2026-04-29 10:00:00'),
                'title'          => 'Sort test',
                'directions'     => 'Past the gate.',
                'latitude'       => 8.4831,
                'longitude'      => 124.6505,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $img1 = $existing[0];
        $img2 = $existing[1];
        $img3 = $existing[2];

        $newTmp = $this->seedTmpPhoto('cover-' . bin2hex(random_bytes(4)) . '.jpg');

        // Submit photos in reordered sequence: new tmp at index 0 (becomes
        // cover), then existing photos in reverse.
        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    $newTmp,
                    ['existing_id' => $img3->id],
                    ['existing_id' => $img2->id],
                    ['existing_id' => $img1->id],
                ],
            ]),
        );

        $listing->refresh()->load('images');
        $sorted = $listing->images->sortBy('sort_order')->values();

        $this->assertCount(4, $sorted);

        // Index 0 is the newly uploaded tmp photo (a freshly created row, not
        // any of the existing IDs).
        $this->assertNotContains($sorted[0]->id, [$img1->id, $img2->id, $img3->id]);

        // Existing photos retained their identity but got renumbered to match
        // their submitted positions.
        $this->assertSame($img3->id, $sorted[1]->id);
        $this->assertSame(1, $sorted[1]->sort_order);

        $this->assertSame($img2->id, $sorted[2]->id);
        $this->assertSame(2, $sorted[2]->sort_order);

        $this->assertSame($img1->id, $sorted[3]->id);
        $this->assertSame(3, $sorted[3]->sort_order);

        // First-in-list becomes the cover.
        $this->assertSame($sorted[0]->id, $listing->display_image_id);
    }

    public function test_it_flips_queue_status_to_visited_on_success(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        );

        $this->assertSame(
            QueueStatus::visited()->value,
            $listing->fresh()->queue_status,
        );
    }

    public function test_it_preserves_assigned_to_and_assigned_at_after_submission(): void
    {
        $marco = $this->asMarco();
        $assignedAt = Carbon::parse('2026-04-29 10:00:00');
        $listing = $this->readyListingFor($marco, ['assigned_at' => $assignedAt]);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        );

        $listing->refresh();
        $this->assertSame($marco->id, $listing->assigned_to);
        $this->assertEquals($assignedAt, $listing->assigned_at);
    }

    public function test_it_records_an_updated_lifecycle_event_with_the_field_officer_as_actor(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        );

        $event = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('updated', $event->event_type);
        $this->assertSame($marco->id, $event->actor_id);
    }

    public function test_it_redirects_to_field_listings_index_on_success(): void
    {
        $marco = $this->asMarco();
        $listing = $this->readyListingFor($marco);

        $this->put(
            route('field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing),
        )->assertRedirect(route('field.listings.index'));
    }
}
