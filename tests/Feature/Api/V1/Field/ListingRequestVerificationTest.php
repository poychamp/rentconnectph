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
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingRequestVerificationTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

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

    private function readyListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()
            ->withImages(2)
            ->create(array_merge([
                'is_verified'        => false,
                'verified_at'        => null,
                'queue_status'       => QueueStatus::assigned()->value,
                'assigned_to'        => $officer->id,
                'assigned_at'        => Carbon::parse('2026-04-29 10:00:00'),
                'title'              => 'Apartment near Capitol University',
                'directions'         => 'Past the green gate.',
                'latitude'           => 8.4831,
                'longitude'          => 124.6505,
                'prequal_status'     => PrequalStatus::calledYes()->value,
                'contact_phone'      => '+639171234567',
                'contact_type'       => ContactType::owner()->value,
                'source_site'        => SourceSite::olx()->value,
                'source_url'         => 'https://olx.ph/original',
                'verification_notes' => 'Calls-team set this.',
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
                [$tmpPhoto], // at least one new visit photo
            ),
        ], $overrides);
    }

    // =========================================================================
    // Auth — bearer token + ability + role
    // =========================================================================

    public function test_it_rejects_guest_with_no_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->patchJson(route('api.v1.field.listings.request-verification', $listing->uuid), [])
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_bearer_token(): void
    {
        $listing = Listing::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->patchJson(route('api.v1.field.listings.request-verification', $listing->uuid), [])
            ->assertUnauthorized();
    }

    public function test_it_rejects_token_without_field_ability(): void
    {
        $user    = $this->makeFieldUser();
        $token   = $user->createToken('hypothetical-admin-device', ['admin'])->plainTextToken;
        $listing = $this->readyListingFor($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.request-verification', $listing->uuid), $this->validPayload($listing))
            ->assertForbidden();
    }

    public function test_it_rejects_user_who_lost_field_role_after_token_issuance(): void
    {
        $user    = User::factory()->create();
        $token   = $user->createToken('field-android', ['field'])->plainTextToken;
        $listing = Listing::factory()
            ->withImages(2)
            ->create([
                'queue_status' => QueueStatus::assigned()->value,
                'assigned_to'  => $user->id,
                'directions'   => 'Past the gate.',
                'latitude'     => 8.4831,
                'longitude'    => 124.6505,
            ])
            ->fresh(['images']);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson(route('api.v1.field.listings.request-verification', $listing->uuid), $this->validPayload($listing))
            ->assertForbidden();
    }

    // =========================================================================
    // Slice — anti-enumeration 404s (cross-tenant / wrong queue / soft-deleted)
    // =========================================================================

    public function test_it_returns_404_for_nonexistent_uuid(): void
    {
        $this->actAsFieldOfficer();

        $this->patchJson(route('api.v1.field.listings.request-verification', (string) Str::uuid7()), [])
            ->assertNotFound();
    }

    public function test_it_returns_404_when_listing_assigned_to_a_different_officer(): void
    {
        $this->actAsFieldOfficer();
        $carlo  = User::factory()->field()->create();
        $carlos = $this->readyListingFor($carlo);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $carlos->uuid),
            $this->validPayload($carlos)
        )->assertNotFound();
    }

    public function test_it_returns_404_when_queue_status_is_not_assigned(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco, [
            'queue_status' => QueueStatus::visited()->value,
        ]);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_listing(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);
        $listing->delete();

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertNotFound();
    }

    // =========================================================================
    // Validation — required fields tighter than update
    // =========================================================================

    public function test_it_requires_title(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['title' => ''])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['title' => 'Title is required.']);
    }

    public function test_it_rejects_title_longer_than_200_characters(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['title' => str_repeat('a', 201)])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['title' => 'Title is too long (max 200 characters).']);
    }

    public function test_it_requires_listing_type(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['listing_type' => ''])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['listing_type' => 'Listing type is required.']);
    }

    public function test_it_requires_barangay(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['barangay' => ''])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['barangay' => 'Barangay is required.']);
    }

    public function test_it_requires_latitude_and_longitude(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['latitude' => null, 'longitude' => null])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_it_requires_directions(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['directions' => ''])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['directions' => 'Directions are required.']);
    }

    public function test_it_rejects_directions_longer_than_500_characters(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['directions' => str_repeat('d', 501)])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['directions' => 'Directions are too long (max 500 characters).']);
    }

    public function test_it_requires_at_least_one_new_photo_uploaded_during_the_session(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        // All existing — no tmp/ uploads. Closure validator catches this.
        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => $this->existingPhotos($listing),
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos' => 'Upload at least one new photo from your visit.']);
    }

    public function test_it_requires_photos_array_to_be_nonempty(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['photos' => []])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos']);
    }

    // =========================================================================
    // Validation — enum / range / format
    // =========================================================================

    public function test_it_rejects_invalid_listing_type(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['listing_type' => 'mansion'])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['listing_type']);
    }

    public function test_it_rejects_invalid_barangay(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['barangay' => 'not_a_real_barangay'])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['barangay']);
    }

    public function test_it_rejects_out_of_bounds_latitude_and_longitude(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['latitude' => 95, 'longitude' => -181])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_it_rejects_verification_notes_longer_than_2000_characters(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['verification_notes' => str_repeat('x', 2001)])
        )->assertUnprocessable()
          ->assertJsonValidationErrors([
              'verification_notes' => 'Verification notes must be 2000 characters or fewer.',
          ]);
    }

    // =========================================================================
    // Validation — numeric type / bounds
    // =========================================================================

    public function test_it_rejects_non_integer_numeric_fields(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'price_monthly' => 12500.5,
                'beds'          => 1.5,
                'baths'         => 1.5,
                'sqm'           => 138.5,
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['price_monthly', 'beds', 'baths', 'sqm']);
    }

    public function test_it_rejects_beds_baths_outside_0_to_20(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['beds' => 21, 'baths' => -1])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['beds', 'baths']);
    }

    public function test_it_rejects_sqm_or_price_monthly_below_one(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['sqm' => -1, 'price_monthly' => 0])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['sqm', 'price_monthly']);
    }

    // =========================================================================
    // Validation — amenities
    // =========================================================================

    public function test_it_rejects_amenity_id_that_does_not_exist(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['amenities' => [99999]])
        )->assertUnprocessable()
          ->assertJsonValidationErrors([
              'amenities.0' => "One or more selected amenities don't exist.",
          ]);
    }

    public function test_it_rejects_amenities_as_slug_strings(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['amenities' => ['parking', 'no_pets']])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['amenities.0', 'amenities.1']);
    }

    // =========================================================================
    // Validation — photos
    // =========================================================================

    public function test_it_rejects_photo_key_that_does_not_start_with_tmp_prefix(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['key' => 'https://images.unsplash.com/photo-1234.jpg'],
                ],
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos.0.key' => 'Invalid photo key.']);
    }

    public function test_it_rejects_photo_with_both_existing_id_and_key(): void
    {
        $marco      = $this->actAsFieldOfficer();
        $listing    = $this->readyListingFor($marco);
        $existingId = $listing->images->first()->id;

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $existingId, 'key' => 'tmp/abc.jpg'],
                ],
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_it_rejects_photo_with_neither_existing_id_nor_key(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        // Include a valid tmp/ photo so the parent `photos` closure rule (which
        // requires ≥1 new visit photo) passes — otherwise it fires first with
        // a `photos` key error and the per-row XOR check never runs. The XOR
        // check fires post-validate in the controller body.
        $tmpPhoto = $this->seedTmpPhoto('valid-' . bin2hex(random_bytes(4)) . '.jpg');

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    $tmpPhoto,
                    ['name' => 'something.jpg'],
                ],
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos.1']);
    }

    public function test_it_rejects_existing_id_referencing_a_different_listings_image(): void
    {
        $marco  = $this->actAsFieldOfficer();
        $marcos = $this->readyListingFor($marco);

        $carlo  = User::factory()->field()->create();
        $carlos = $this->readyListingFor($carlo);
        $carlosPhotoId = $carlos->images->first()->id;

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $marcos->uuid),
            $this->validPayload($marcos, [
                'photos' => [
                    ['existing_id' => $carlosPhotoId],
                ],
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['photos.0.existing_id']);
    }

    public function test_it_rejects_too_many_amenities_or_photos(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);
        $existingPhotoId = $listing->images->first()->id;

        $amenityIds = [];
        for ($i = 0; $i < 51; $i++) {
            $amenityIds[] = Amenity::factory()->create()->id;
        }

        $photos = array_fill(0, 21, ['existing_id' => $existingPhotoId]);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'amenities' => $amenityIds,
                'photos'    => $photos,
            ])
        )->assertUnprocessable()
          ->assertJsonValidationErrors(['amenities', 'photos']);
    }

    // =========================================================================
    // Persistence — editable fields
    // =========================================================================

    public function test_it_persists_editable_scalar_fields(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco, [
            'title'         => 'Original Title',
            'price_monthly' => 9999,
            'beds'          => 1,
        ]);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
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
            ])
        )->assertOk();

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

    public function test_it_syncs_amenities(): void
    {
        $marco  = $this->actAsFieldOfficer();
        $wifi   = Amenity::firstOrCreate(['slug' => 'wifi'],   ['name' => 'Wi-Fi',   'icon' => 'wifi',   'sort_order' => 1]);
        $aircon = Amenity::firstOrCreate(['slug' => 'aircon'], ['name' => 'Air-con', 'icon' => 'aircon', 'sort_order' => 2]);

        $listing = $this->readyListingFor($marco);
        $listing->amenities()->sync([$wifi->id]);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['amenities' => [$aircon->id]])
        )->assertOk();

        $listing->refresh();
        $this->assertEqualsCanonicalizing(
            [$aircon->id],
            $listing->amenities->pluck('id')->all()
        );
    }

    public function test_it_persists_photo_sort_order_matching_the_submitted_array_order(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => QueueStatus::assigned()->value,
                'assigned_to'  => $marco->id,
                'directions'   => 'Past the gate.',
                'latitude'     => 8.4831,
                'longitude'    => 124.6505,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $img1 = $existing[0];
        $img2 = $existing[1];
        $img3 = $existing[2];

        $newTmp = $this->seedTmpPhoto('cover-' . bin2hex(random_bytes(4)) . '.jpg');

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    $newTmp,
                    ['existing_id' => $img3->id],
                    ['existing_id' => $img2->id],
                    ['existing_id' => $img1->id],
                ],
            ])
        )->assertOk();

        $listing->refresh()->load('images');
        $sorted = $listing->images->sortBy('sort_order')->values();

        $this->assertCount(4, $sorted);
        $this->assertNotContains($sorted[0]->id, [$img1->id, $img2->id, $img3->id]);

        $this->assertSame($img3->id, $sorted[1]->id);
        $this->assertSame(1, $sorted[1]->sort_order);

        $this->assertSame($img2->id, $sorted[2]->id);
        $this->assertSame(2, $sorted[2]->sort_order);

        $this->assertSame($img1->id, $sorted[3]->id);
        $this->assertSame(3, $sorted[3]->sort_order);

        $this->assertSame($sorted[0]->id, $listing->display_image_id);
    }

    public function test_it_deletes_photos_that_are_not_in_the_submitted_array(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => QueueStatus::assigned()->value,
                'assigned_to'  => $marco->id,
                'directions'   => 'Past the gate.',
                'latitude'     => 8.4831,
                'longitude'    => 124.6505,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $kept     = $existing[0];
        $removedA = $existing[1];
        $removedB = $existing[2];

        $tmpPhoto = $this->seedTmpPhoto('new-' . bin2hex(random_bytes(4)) . '.jpg');

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $kept->id],
                    $tmpPhoto,
                ],
            ])
        )->assertOk();

        $listing->refresh()->load('images');

        $this->assertCount(2, $listing->images);
        $this->assertContains($kept->id, $listing->images->pluck('id')->all());
        $this->assertNull(ListingImage::find($removedA->id));
        $this->assertNull(ListingImage::find($removedB->id));
    }

    public function test_it_keeps_existing_photos_and_persists_new_tmp_uploads(): void
    {
        $marco    = $this->actAsFieldOfficer();
        $listing  = $this->readyListingFor($marco);
        $existing = $listing->images->first();

        $tmpPhoto = $this->seedTmpPhoto('new-' . bin2hex(random_bytes(4)) . '.jpg');

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $existing->id],
                    $tmpPhoto,
                ],
            ])
        )->assertOk();

        $listing->refresh();
        $this->assertCount(2, $listing->images);
        $this->assertContains($existing->id, $listing->images->pluck('id')->all());
    }

    // =========================================================================
    // State flip — queue_status: assigned → visited, visited_at = now (atomic)
    // =========================================================================

    public function test_it_flips_queue_status_to_visited_on_success(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertOk();

        $this->assertSame(
            QueueStatus::visited()->value,
            $listing->fresh()->queue_status,
        );
    }

    public function test_it_sets_visited_at_to_now_when_flipping_queue_status_to_visited(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco, ['visited_at' => null]);

        Carbon::setTestNow(Carbon::parse('2026-05-15 09:30:00'));

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertOk();

        $listing->refresh();
        $this->assertSame(QueueStatus::visited()->value, $listing->queue_status);
        $this->assertNotNull($listing->visited_at);
        $this->assertTrue($listing->visited_at->equalTo(Carbon::parse('2026-05-15 09:30:00')));

        Carbon::setTestNow();
    }

    public function test_it_does_not_flip_state_when_validation_fails(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco, ['visited_at' => null]);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, ['photos' => []])
        )->assertUnprocessable();

        $listing->refresh();
        $this->assertSame(QueueStatus::assigned()->value, $listing->queue_status);
        $this->assertNull($listing->visited_at);
    }

    // =========================================================================
    // Security boundary — locked fields silently ignored
    // =========================================================================

    public function test_it_silently_ignores_calls_team_locked_fields_in_the_request(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'contact_phone'  => '+639170000000',
                'contact_type'   => ContactType::authorizedRep()->value,
                'source_site'    => SourceSite::facebookGroup()->value,
                'source_url'     => 'https://attacker.example/owned',
                'prequal_status' => PrequalStatus::notCalled()->value,
            ])
        )->assertOk();

        $listing->refresh();
        $this->assertSame('+639171234567',                   $listing->contact_phone);
        $this->assertSame(ContactType::owner()->value,       $listing->contact_type);
        $this->assertSame(SourceSite::olx()->value,          $listing->source_site);
        $this->assertSame('https://olx.ph/original',         $listing->source_url);
        $this->assertSame(PrequalStatus::calledYes()->value, $listing->prequal_status);
    }

    public function test_it_silently_ignores_admin_owned_assignment_and_moderation_fields(): void
    {
        $marco      = $this->actAsFieldOfficer();
        $assignedAt = Carbon::parse('2026-04-29 10:00:00');
        $listing    = $this->readyListingFor($marco, ['assigned_at' => $assignedAt]);

        $other = User::factory()->field()->create();

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'assigned_to' => $other->id,
                'assigned_at' => Carbon::parse('2030-01-01'),
                'is_verified' => true,
                'verified_at' => Carbon::parse('2030-01-01'),
            ])
        )->assertOk();

        $listing->refresh();
        $this->assertSame($marco->id,          $listing->assigned_to);
        $this->assertEquals($assignedAt,       $listing->assigned_at);
        $this->assertFalse((bool) $listing->is_verified);
        $this->assertNull($listing->verified_at);
    }

    public function test_it_allows_field_officer_to_override_verification_notes(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing, [
                'verification_notes' => 'Visited Tue 10am — gate matches photos.',
            ])
        )->assertOk();

        $listing->refresh();
        $this->assertSame(
            'Visited Tue 10am — gate matches photos.',
            $listing->verification_notes,
        );
    }

    // =========================================================================
    // Lifecycle audit event
    // =========================================================================

    public function test_it_records_an_updated_lifecycle_event_with_the_field_officer_as_actor(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertOk();

        $event = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('updated', $event->event_type);
        $this->assertSame($marco->id, $event->actor_id);
    }

    // =========================================================================
    // Success response shape — `{success: true}` minimal envelope
    // =========================================================================

    public function test_it_returns_200_with_success_envelope(): void
    {
        $marco   = $this->actAsFieldOfficer();
        $listing = $this->readyListingFor($marco);

        $this->patchJson(
            route('api.v1.field.listings.request-verification', $listing->uuid),
            $this->validPayload($listing)
        )->assertOk()
          ->assertExactJson(['success' => true]);
    }

    // =========================================================================
    // Middleware introspection
    // =========================================================================

    public function test_it_lives_in_api_middleware_group_with_sanctum_field_abilities(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.field.listings.request-verification');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('abilities:field', $middleware);
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $middleware);
    }
}
