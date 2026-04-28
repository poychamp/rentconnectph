<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
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
            ->state($state)
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
            'monthly_rent' => 18500,
            'barangay'     => 'pueblo_de_oro',
            'beds'         => 2,
            'baths'        => 1,
            'sqft'         => 65,
            'latitude'     => 8.4542,
            'longitude'    => 124.6411,
            'amenities'    => [],
            'photos'       => $this->existingPhotos($listing),
            'is_verified'  => true,
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

        $response->assertRedirect(route('admin.login'));
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
            'monthly_rent' => null,
            'barangay'     => '',
            'beds'         => null,
            'baths'        => null,
            'sqft'         => null,
            'photos'       => [],
            'is_verified'  => null,
        ]);

        $response->assertSessionHasErrors([
            'title'        => 'Title is required.',
            'listing_type' => 'Listing type is required.',
            'monthly_rent' => 'Monthly rent is required.',
            'barangay'     => 'Barangay is required.',
            'beds'         => 'Bedrooms is required.',
            'baths'        => 'Bathrooms is required.',
            'sqft'         => 'Floor area is required.',
            'photos'       => 'At least one photo is required.',
            'is_verified'  => 'Verified flag is required.',
        ]);
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
            'monthly_rent' => 18500,
            'barangay'     => 'atlantis',                    // not in enum
            'beds'         => 2,
            'baths'        => 1,
            'sqft'         => 65,
            'latitude'     => 200,                            // out of range
            'longitude'    => 500,                            // out of range
            'amenities'    => [999999],                       // doesn't exist
            'photos'       => [
                ['key' => 'listings/foreign-uuid/cover.jpg', 'name' => 'x', 'size' => 1], // outside tmp/
                ['existing_id' => $listingB->images->first()->id],                          // foreign listing's image
            ],
            'is_verified'  => true,
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
            'monthly_rent' => 22500,
            'barangay'     => 'carmen',
            'beds'         => 3,
            'baths'        => 2,
            'sqft'         => 90,
            'latitude'     => 8.4635,
            'longitude'    => 124.6573,
            'amenities'    => [$newAmenityA->id, $newAmenityB->id],
            'photos'       => $photos,
            'is_verified'  => true,
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
        $this->assertSame(90, $listing->sqft);
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
    // is_verified transitions (3 — behaviorally distinct)
    // =========================================================================

    public function test_it_sets_verified_at_to_now_when_toggling_false_to_true(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, ['is_verified' => false, 'verified_at' => null]);

        Carbon::setTestNow('2026-04-27 12:00:00');

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'is_verified' => true,
        ]));

        $listing->refresh();
        $this->assertTrue($listing->is_verified);
        $this->assertSame('2026-04-27 12:00:00', $listing->verified_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_it_sets_verified_at_to_null_when_toggling_true_to_false(): void
    {
        $this->asAdmin();
        $listing = $this->makeListing(2, [
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'is_verified' => false,
        ]));

        $listing->refresh();
        $this->assertFalse($listing->is_verified);
        $this->assertNull($listing->verified_at);
    }

    public function test_it_leaves_verified_at_unchanged_when_no_transition(): void
    {
        $this->asAdmin();
        $original = Carbon::parse('2026-01-15 09:30:00');
        $listing = $this->makeListing(2, ['is_verified' => true, 'verified_at' => $original]);

        Carbon::setTestNow('2026-04-27 12:00:00');

        $this->put(route('admin.listings.update', $listing->uuid), $this->validPayload($listing, [
            'is_verified' => true,
        ]));

        $listing->refresh();
        $this->assertTrue($listing->is_verified);
        $this->assertSame($original->format('Y-m-d H:i:s'), $listing->verified_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
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
}
