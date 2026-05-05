<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingUpdateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    protected function seedTmpPhoto(string $key): array
    {
        Storage::disk('s3')->put("tmp/{$key}", 'fake-image-bytes');
        return ['name' => "{$key}.jpg", 'size' => 12345, 'key' => "tmp/{$key}"];
    }

    protected function makeListing(int $imageCount = 2, array $state = []): Listing
    {
        return Listing::factory()
            ->withImages($imageCount)
            ->state(array_merge([
                'is_verified' => true,
                'verified_at' => Carbon::parse('2026-01-01 00:00:00'),
            ], $state))
            ->create()
            ->fresh(['images', 'amenities']);
    }

    protected function existingPhotos(Listing $listing): array
    {
        return $listing->images
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($img) => ['existing_id' => $img->id])
            ->all();
    }

    protected function validPayload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Updated Title',
            'description'  => 'Updated description.',
            'listing_type' => 'apartment',
            'price_monthly' => 18500,
            'barangay'     => 'pueblo_de_oro',
            'beds'         => 2,
            'baths'        => 1,
            'sqm'          => 65,
            'latitude'     => 8.4542,
            'longitude'    => 124.6411,
            'amenities'    => [],
            'photos'       => $this->existingPhotos($listing),
            'is_featured'  => false,
        ], $overrides);
    }

    protected function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    // =========================================================================
    // Auth + access (2)
    // =========================================================================

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->makeListing();

        $response = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_returns_404_for_unknown_or_soft_deleted_uuid(): void
    {
        $this->asAdmin();

        $this->put(route('admin.listings.update', '019dc5d9-ff77-7381-9755-000000000000'), [])
            ->assertNotFound();

        $listing = $this->makeListing();
        $listing->delete();
        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertNotFound();
    }

    // =========================================================================
    // Validation — required fields (one comprehensive pass, key→msg pairs)
    // =========================================================================

    public function test_it_validates_required_fields_with_messages(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing();

        $response = $this->put(route('admin.listings.update', $listing->uuid), [
            // every required field omitted / empty
            'title'        => '',
            'listing_type' => '',
            'price_monthly' => null,
            'barangay'     => '',
            'beds'         => null,
            'baths'        => null,
            'sqm'          => null,
            'photos'       => [],
        ]);

        $response->assertSessionHasErrors([
            'title'        => 'Title is required.',
            'listing_type' => 'Listing type is required.',
            'price_monthly' => 'Monthly rent is required.',
            'barangay'     => 'Barangay is required.',
            'latitude'     => 'Latitude is required.',
            'longitude'    => 'Longitude is required.',
            'photos'       => 'At least one photo is required.',
        ]);

        // beds / baths / sqm are now nullable — must NOT fire required errors.
        $response->assertSessionDoesntHaveErrors(['beds', 'baths', 'sqm']);
    }

    // =========================================================================
    // Validation — invalid values (one comprehensive pass, key→msg pairs)
    // =========================================================================

    public function test_it_validates_invalid_values_with_messages(): void
    {
        $this->asAdmin();
        $listingA = $this->makeListing(1);
        $listingB = $this->makeListing(1);

        $response = $this->put(route('admin.listings.update', $listingA->uuid), [
            'title'        => 'ok',
            'listing_type' => 'spaceship',                   // not in enum
            'price_monthly' => 18500,
            'barangay'     => 'atlantis',                    // not in enum
            'beds'         => 2,
            'baths'        => 1,
            'sqm'          => 65,
            'latitude'     => 200,                            // out of range
            'longitude'    => 500,                            // out of range
            'amenities'    => [999999],                       // doesn't exist
            'photos'       => [
                ['key' => 'listings/foreign-uuid/cover.jpg', 'name' => 'x', 'size' => 1], // outside tmp/
                ['existing_id' => $listingB->images->first()->id],                          // foreign listing's image
            ],
            'is_featured'  => false,
        ]);

        $response->assertSessionHasErrors([
            'listing_type'           => 'Invalid listing type.',
            'barangay'               => 'Invalid barangay.',
            'latitude'               => 'Latitude must be between -90 and 90.',
            'longitude'              => 'Longitude must be between -180 and 180.',
            'amenities.0'            => "One or more selected amenities don't exist.",
            'photos.0.key'           => 'Invalid photo key.',
            'photos.1.existing_id',                                                         // Rule::in($existingIds) failure
        ]);
    }

    // =========================================================================
    // Validation — photo XOR (each entry must have exactly one of existing_id/key)
    // =========================================================================

    public function test_it_rejects_photo_entries_with_both_or_neither_id_and_key(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(1);
        $existing = $listing->images->first();

        // BOTH existing_id and key on one entry
        $bothResponse = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'photos' => [['existing_id' => $existing->id, 'key' => 'tmp/x', 'name' => 'x', 'size' => 1]],
        ]));
        $bothResponse->assertSessionHasErrors('photos.0');

        // NEITHER existing_id nor key
        $neitherResponse = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'photos' => [['name' => 'x', 'size' => 1]],
        ]));
        $neitherResponse->assertSessionHasErrors('photos.0');
    }

    // =========================================================================
    // Validation — photo array bounds (too many)
    // =========================================================================

    public function test_it_rejects_too_many_photos(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2);

        $photos = $this->existingPhotos($listing);
        for ($i = 0; $i < 19; $i++) {
            $photos[] = $this->seedTmpPhoto("new-{$i}");
        }

        $response = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'photos' => $photos,
        ]));

        $response->assertSessionHasErrors(['photos' => 'Maximum 20 photos allowed.']);
    }

    // =========================================================================
    // Happy path — comprehensive end-to-end
    // =========================================================================

    public function test_it_updates_listing_end_to_end(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(3);

        $oldAmenity = Amenity::factory()->create();
        $newAmenityA = Amenity::factory()->create();
        $newAmenityB = Amenity::factory()->create();
        $listing->amenities()->sync([$oldAmenity->id]);

        $sorted = $listing->images->sortBy('sort_order')->values();
        [$first, $second, $third] = [$sorted[0], $sorted[1], $sorted[2]];

        // Seed S3 file for the existing image we'll remove (so the post-commit
        // S3 delete can find and erase it).
        $removedKey = ltrim(parse_url($first->url, PHP_URL_PATH) ?? '', '/');
        Storage::disk('s3')->put($removedKey, 'fake-bytes');

        // Submitted post-edit photo list:
        //   [0] new tmp upload (will be cover)
        //   [1] existing third image
        //   [2] existing second image
        //   (first image omitted → should be deleted)
        $newPhoto = $this->seedTmpPhoto('brand-new');
        $photos = [
            $newPhoto,
            ['existing_id' => $third->id],
            ['existing_id' => $second->id],
        ];

        $response = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'title'        => 'Brand New Title',
            'description'  => 'Brand new desc.',
            'listing_type' => 'condo',
            'price_monthly' => 22500,
            'barangay'     => 'carmen',
            'beds'         => 3,
            'baths'        => 2,
            'sqm'          => 90,
            'latitude'     => 8.4635,
            'longitude'    => 124.6573,
            'amenities'    => [$newAmenityA->id, $newAmenityB->id],
            'photos'       => $photos,
            'is_featured'  => true,
        ]));

        // Redirect + flash
        $response->assertRedirect(route('admin.verified-listings.index'));
        $response->assertSessionHas('success');

        // Scalar field updates
        $listing->refresh();
        $this->assertSame('Brand New Title', $listing->title);
        $this->assertSame('Brand new desc.', $listing->description);
        $this->assertSame('condo', $listing->type);
        $this->assertSame(22500, $listing->price_monthly);
        $this->assertSame('carmen', $listing->barangay);
        $this->assertSame(3, $listing->beds);
        $this->assertSame(2, $listing->baths);
        $this->assertSame(90, $listing->sqm);
        $this->assertEqualsWithDelta(8.4635,   $listing->latitude,  0.0000001);
        $this->assertEqualsWithDelta(124.6573, $listing->longitude, 0.0000001);
        $this->assertTrue($listing->is_verified);
        $this->assertTrue($listing->is_featured);

        // Photo removal: first image is gone (row + S3 file)
        $this->assertNull(ListingImage::find($first->id));
        Storage::disk('s3')->assertMissing($removedKey);

        // Surviving + new photos with correct sort_order
        $images = $listing->fresh()->images()->orderBy('sort_order')->get();
        $this->assertCount(3, $images);
        $this->assertSame(0, $images[0]->sort_order); // new upload
        $this->assertSame($third->id,  $images[1]->id);
        $this->assertSame(1, $images[1]->sort_order);
        $this->assertSame($second->id, $images[2]->id);
        $this->assertSame(2, $images[2]->sort_order);

        // display_image_id = first item (the new upload)
        $this->assertSame($images[0]->id, $listing->fresh()->display_image_id);

        // New photo: tmp/ copied to listings/<uuid>/<filename>, original tmp/ cleaned up
        Storage::disk('s3')->assertExists("listings/{$listing->uuid}/brand-new");
        Storage::disk('s3')->assertMissing('tmp/brand-new');

        // Amenities synced (replaces old)
        $attached = $listing->fresh()->amenities->pluck('id')->sort()->values()->all();
        $expected = collect([$newAmenityA->id, $newAmenityB->id])->sort()->values()->all();
        $this->assertSame($expected, $attached);
    }

    // =========================================================================
    // Origin-aware redirect: ?from=unverified returns to unverified-listings,
    // ?from=verified (or absent) returns to verified-listings. Lets the admin
    // land back on the page they were editing from instead of always landing
    // on Verified Listings.
    // =========================================================================

    public function test_it_redirects_to_origin_after_update_based_on_from_param(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing();

        // from=unverified → land on Unverified Listings
        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['from' => 'unverified'])
        )->assertRedirect(route('admin.unverified-listings.index'));

        // from=verified → land on Verified Listings
        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['from' => 'verified'])
        )->assertRedirect(route('admin.verified-listings.index'));

        // from=featured → land on Featured Listings
        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['from' => 'featured'])
        )->assertRedirect(route('admin.featured-listings.index'));

        // no from → default to Verified Listings (preserves existing behavior)
        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing)
        )->assertRedirect(route('admin.verified-listings.index'));

        // Garbage from value → falls through to default (Verified). Don't 422
        // on a UI-only field — just be defensive and ignore unknown values.
        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['from' => 'random-garbage'])
        )->assertRedirect(route('admin.verified-listings.index'));
    }

    // =========================================================================
    // Slice guard — verified-only update endpoint
    // =========================================================================

    public function test_it_returns_404_when_listing_is_not_verified(): void
    {
        $this->asAdmin();

        $unverified = Listing::factory()
            ->withImages(2)
            ->state(['is_verified' => false, 'verified_at' => null])
            ->create();

        $this->put(route('admin.listings.update', $unverified->uuid), $this->validPayload($unverified))
            ->assertNotFound();
    }

    // =========================================================================
    // Security boundary — read-only / locked fields are silent-ignored
    // =========================================================================

    public function test_it_silently_ignores_read_only_fields_in_request(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, [
            'contact_phone'      => '+639170000000',
            'source_site'        => 'rent_ph',
            'source_url'         => 'https://rent.ph/orig',
            'contact_type'       => 'owner',
            'directions'         => 'Original directions.',
            'assigned_to'        => null,
            'assigned_at'        => null,
            'visited_at'         => Carbon::parse('2026-04-01 00:00:00'),
            'queue_status'       => 'visited',
            'prequal_status'     => null,
            'is_field_priority'  => false,
            'field_priority_order' => null,
        ]);
        $originalVerifiedAt = $listing->verified_at->format('Y-m-d H:i:s');

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'is_verified'          => false,
            'contact_phone'        => '+639179999999',
            'source_site'          => 'olx',
            'source_url'           => 'https://attacker.test/x',
            'contact_type'         => 'broker',
            'directions'           => 'Hijacked directions.',
            'assigned_to'          => 99999,
            'assigned_at'          => Carbon::parse('2030-12-31'),
            'visited_at'           => Carbon::parse('2030-12-31'),
            'verified_at'          => Carbon::parse('2030-12-31'),
            'queue_status'         => 'unassigned',
            'prequal_status'       => 'called_yes',
            'is_field_priority'    => true,
            'field_priority_order' => 999,
        ]));

        $listing->refresh();
        $this->assertTrue($listing->is_verified);
        $this->assertSame($originalVerifiedAt, $listing->verified_at->format('Y-m-d H:i:s'));
        $this->assertSame('+639170000000', $listing->contact_phone);
        $this->assertSame('rent_ph', $listing->source_site);
        $this->assertSame('https://rent.ph/orig', $listing->source_url);
        $this->assertSame('owner', $listing->contact_type);
        $this->assertSame('Original directions.', $listing->directions);
        $this->assertNull($listing->assigned_to);
        $this->assertNull($listing->assigned_at);
        $this->assertSame('2026-04-01', $listing->visited_at->format('Y-m-d'));
        $this->assertSame('visited', $listing->queue_status);
        $this->assertNull($listing->prequal_status);
        $this->assertFalse((bool) $listing->is_field_priority);
        $this->assertNull($listing->field_priority_order);
    }

    // =========================================================================
    // verification_notes — admin-editable on verified update (calls-team
    // surface — they amend notes after qualifying calls)
    // =========================================================================

    public function test_it_persists_verification_notes_when_provided(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, [
            'verification_notes' => 'Original notes.',
        ]);

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'verification_notes' => 'Confirmed owner direct after call. Tenant moving in mid-month.',
        ]));

        $this->assertSame(
            'Confirmed owner direct after call. Tenant moving in mid-month.',
            $listing->fresh()->verification_notes,
        );
    }

    public function test_it_clears_verification_notes_when_submitted_empty(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, [
            'verification_notes' => 'Stale notes to clear.',
        ]);

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'verification_notes' => '',
        ]));

        $this->assertNull($listing->fresh()->verification_notes);
    }

    public function test_it_validates_verification_notes_max_length(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2);

        $response = $this->from(route('admin.listings.edit', $listing->uuid))
            ->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
                'verification_notes' => str_repeat('a', 2001),
            ]));

        $response->assertSessionHasErrors([
            'verification_notes' => 'Verification notes are too long (max 2000 characters).',
        ]);
    }

    // =========================================================================
    // Removal — non-S3 URL is silently ignored (no S3 delete attempted)
    // =========================================================================

    public function test_it_silently_ignores_non_s3_url_when_deleting_existing_image(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2);

        // Factory URLs are external (Unsplash). DON'T seed any S3 object —
        // the controller's best-effort S3 delete should be a no-op for keys
        // that don't exist, not a crash.
        $sorted = $listing->images->sortBy('sort_order')->values();
        $kept    = $sorted[0];
        $removed = $sorted[1];

        $maybeKey = ltrim(parse_url($removed->url, PHP_URL_PATH) ?? '', '/');
        Storage::disk('s3')->assertMissing($maybeKey); // setup sanity

        $response = $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'photos' => [['existing_id' => $kept->id]],
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertNull(ListingImage::find($removed->id));
        $this->assertNotNull(ListingImage::find($kept->id));
    }

    // =========================================================================
    // Rollback
    // =========================================================================

    public function test_it_rolls_back_when_s3_copy_fails(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(1);
        $originalTitle = $listing->title;
        $originalImageCount = $listing->images()->count();

        // tmp key never seeded → copy() returns false → controller throws → tx rollback
        $brokenPhoto = ['key' => 'tmp/never-existed', 'name' => 'x.jpg', 'size' => 1];
        $photos = array_merge($this->existingPhotos($listing), [$brokenPhoto]);

        try {
            $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
                'title'  => 'Should Not Persist',
                'photos' => $photos,
            ]));
        } catch (\Throwable $e) {
            // Transaction throws — surfaced through TestCase.
        }

        $this->assertSame($originalTitle, $listing->fresh()->title);
        $this->assertSame($originalImageCount, $listing->fresh()->images()->count());
    }

    // =========================================================================
    // Lifecycle audit log
    // =========================================================================

    public function test_it_writes_an_updated_lifecycle_event_after_updating_listing(): void
    {
        $admin = $this->asAdmin();
        $listing = $this->makeListing();

        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['title' => 'New Title After Edit'])
        );

        $events = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->where('event_type', 'updated')
            ->get();

        $this->assertCount(1, $events, 'Exactly one updated event must land per update call');

        $event = $events->first();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertNull($event->reason, 'Reason is null for created/updated events');

        $this->assertNotNull($event->notes);
        $notes = json_decode($event->notes, true);
        $this->assertIsArray($notes, 'notes must be valid JSON');
        $this->assertSame('New Title After Edit', $notes['title']);
        $this->assertArrayNotHasKey('_token', $notes);
        $this->assertArrayNotHasKey('_method', $notes);
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');
        $listing = $this->makeListing();

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertForbidden();
    }

    public function test_it_silently_ignores_listed_at_in_request_body(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, [
            'listed_at' => Carbon::parse('2026-02-01 00:00:00'),
        ]);
        $originalListedAt = $listing->listed_at->format('Y-m-d H:i:s');

        $this->put(
            route('admin.listings.update', $listing->uuid),
            $this->validPayload($listing, ['listed_at' => '2030-12-31 23:59:59'])
        );

        $listing->refresh();
        $this->assertSame($originalListedAt, $listing->listed_at->format('Y-m-d H:i:s'));
    }
}
