<?php

namespace Tests\Feature\Admin;

use App\Enums\SourceSite;
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

class AdminListingStoreTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    /**
     * Seed a tmp/<key> file on the fake S3 disk so the controller's
     * Storage::disk('s3')->copy(tmp/<key>, listings/<uuid>/<key>) succeeds.
     * Returns the photo array shape the form sends.
     */
    protected function seedTmpPhoto(string $key): array
    {
        Storage::disk('s3')->put("tmp/{$key}", 'fake-image-bytes');
        return [
            'name' => "{$key}.jpg",
            'size' => 12345,
            'key'  => "tmp/{$key}",
        ];
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Modern 2-BR in Pueblo de Oro',
            'description'   => 'Lovely place near the park.',
            'listing_type'  => 'apartment',
            'price_monthly'  => 18500,
            'barangay'      => 'pueblo_de_oro',
            'beds'          => 2,
            'baths'         => 1,
            'sqm'           => 65,
            'latitude'      => 8.4542,
            'longitude'     => 124.6411,
            'amenities'     => [],
            'photos'        => [$this->seedTmpPhoto('photo-1')],
            'contact_phone' => '09171234567',
            'intent'        => 'publish',
        ], $overrides);
    }

    // =========================================================================
    // Auth + access (2)
    // =========================================================================

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->post(route('admin.listings.store'), $this->validPayload());

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_allows_authenticated_super_admin_to_post(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.listings.store'), $this->validPayload());

        $response->assertRedirect();    // 302 success
        $response->assertSessionHasNoErrors();
    }

    // =========================================================================
    // Validation — required fields (1) — only title remains required per FRD-023
    // =========================================================================

    public function test_it_rejects_when_title_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['title']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasErrors([
                 'title' => 'Title is required.',
             ]);
    }

    // =========================================================================
    // Validation — invalid values (8)
    // =========================================================================

    public function test_it_rejects_invalid_listing_type(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'listing_type' => 'mansion',
        ]))->assertSessionHasErrors([
            'listing_type' => 'Invalid listing type.',
        ]);
    }

    public function test_it_rejects_invalid_barangay(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'barangay' => 'mars',
        ]))->assertSessionHasErrors([
            'barangay' => 'Invalid barangay.',
        ]);
    }

    public function test_it_rejects_amenity_id_that_doesnt_exist(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'amenities' => [99999],
        ]))->assertSessionHasErrors([
            'amenities.0' => "One or more selected amenities don't exist.",
        ]);
    }

    public function test_it_rejects_photo_key_outside_tmp_namespace(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Security check — attacker submits a key NOT in tmp/ to try to
        // rename / repath an existing listing's image. Must be rejected.
        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [[
                'name' => 'evil.jpg',
                'size' => 1234,
                'key'  => 'listings/some-other-uuid/cover.jpg',
            ]],
        ]))->assertSessionHasErrors([
            'photos.0.key' => 'Invalid photo key.',
        ]);
    }

    public function test_it_rejects_lat_out_of_range(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'latitude' => 200,
        ]))->assertSessionHasErrors([
            'latitude' => 'Latitude must be between -90 and 90.',
        ]);
    }

    public function test_it_rejects_lng_out_of_range(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'longitude' => -300,
        ]))->assertSessionHasErrors([
            'longitude' => 'Longitude must be between -180 and 180.',
        ]);
    }

    public function test_it_rejects_too_many_photos(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $photos = [];
        for ($i = 0; $i < 21; $i++) {
            $photos[] = $this->seedTmpPhoto("photo-{$i}");
        }

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => $photos,
        ]))->assertSessionHasErrors([
            'photos' => 'Maximum 20 photos allowed.',
        ]);
    }

    public function test_it_rejects_negative_beds(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // No custom message for beds.min in FRD spec — uses Laravel's default
        // ("The beds field must be at least 0.") so just assert field has an error.
        $this->post(route('admin.listings.store'), $this->validPayload([
            'beds' => -1,
        ]))->assertSessionHasErrors('beds');
    }

    // =========================================================================
    // Validation — relaxed nullable acceptance (4) — FRD-023
    // =========================================================================

    public function test_it_accepts_when_beds_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['beds']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->beds);
    }

    public function test_it_accepts_when_baths_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['baths']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->baths);
    }

    public function test_it_accepts_when_sqm_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['sqm']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->sqm);
    }

    public function test_it_accepts_when_photos_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [],
        ]))->assertSessionHasNoErrors();

        $listing = Listing::first();
        $this->assertNotNull($listing);
        $this->assertSame(0, $listing->images()->count());
        $this->assertNull($listing->display_image_id);
    }

    public function test_it_accepts_when_listing_type_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['listing_type']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->type);
    }

    public function test_it_accepts_when_price_monthly_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['price_monthly']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->price_monthly);
    }

    public function test_it_accepts_when_barangay_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['barangay']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasNoErrors();

        $this->assertNull(Listing::first()->barangay);
    }

    // =========================================================================
    // Validation — source fields (5) — FRD-023
    // =========================================================================

    public function test_it_persists_each_valid_source_site(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        foreach (SourceSite::toValues() as $i => $value) {
            $this->post(route('admin.listings.store'), $this->validPayload([
                'title'       => "Listing for {$value}",
                'source_site' => $value,
                'photos'      => [$this->seedTmpPhoto("photo-{$i}")],
            ]))->assertSessionHasNoErrors();
        }

        $persisted = Listing::pluck('source_site')->all();
        sort($persisted);

        $expected = SourceSite::toValues();
        sort($expected);

        $this->assertSame($expected, $persisted);
    }

    public function test_it_persists_source_url_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $url = 'https://www.olx.ph/item/2-br-pueblo-de-oro-cdo-12345';
        $this->post(route('admin.listings.store'), $this->validPayload([
            'source_url' => $url,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($url, Listing::first()->source_url);
    }

    public function test_it_validates_source_site_against_enum_allowlist(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'source_site' => 'craigslist',
        ]))->assertSessionHasErrors(['source_site' => 'Invalid source site.']);
    }

    public function test_it_validates_source_url_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'source_url' => 'not-a-url',
        ]))->assertSessionHasErrors(['source_url' => 'Source URL must be a valid URL.']);
    }

    public function test_it_validates_source_url_max_length(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $tooLong = 'https://example.com/' . str_repeat('a', 2000);  // > 2000 chars total

        $this->post(route('admin.listings.store'), $this->validPayload([
            'source_url' => $tooLong,
        ]))->assertSessionHasErrors(['source_url' => 'Source URL is too long (max 2000 characters).']);
    }

    // =========================================================================
    // Happy path — Listing creation (6)
    // =========================================================================

    public function test_it_creates_listing_with_correct_field_mapping(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'title'        => 'Modern 2-BR in Pueblo de Oro',
            'description'  => 'Lovely place.',
            'listing_type' => 'apartment',     // form 'listing_type' → DB 'type'
            'price_monthly' => 18500,
            'barangay'     => 'pueblo_de_oro',
            'beds'         => 2,
            'baths'        => 1,
            'sqm'          => 65,
        ]));

        $listing = Listing::first();
        $this->assertNotNull($listing);
        $this->assertSame('Modern 2-BR in Pueblo de Oro', $listing->title);
        $this->assertSame('Lovely place.', $listing->description);
        $this->assertSame('apartment', $listing->type);                 // mapped
        $this->assertSame(18500, $listing->price_monthly);              // mapped
        $this->assertSame('pueblo_de_oro', $listing->barangay);
        $this->assertSame(2, $listing->beds);
        $this->assertSame(1, $listing->baths);
        $this->assertSame(65, $listing->sqm);
    }

    public function test_it_creates_listing_as_unverified_with_null_verified_at(): void
    {
        // Per FRD-023: queue rows are always created unverified. Mark-Verified
        // is a future PRD action (post-field-visit).
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload());

        $listing = Listing::first();
        $this->assertFalse((bool) $listing->is_verified);
        $this->assertNull($listing->verified_at);
    }

    public function test_it_sets_lat_lng_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'latitude'  => 8.4542,
            'longitude' => 124.6411,
        ]));

        $listing = Listing::first();
        $this->assertEquals(8.4542, $listing->latitude);
        $this->assertEquals(124.6411, $listing->longitude);
    }

    public function test_it_preserves_lat_lng_precision_and_type(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 7-decimal precision is the limit of the decimal(10,7) column —
        // also matches Mapbox's toFixed(7) on marker drag/click.
        $this->post(route('admin.listings.store'), $this->validPayload([
            'latitude'  => 8.4542123,
            'longitude' => 124.6411567,
        ]));

        $listing = Listing::first();

        // Type must be float, not string. The Listing model casts decimal columns
        // because some DB drivers return strings for decimal by default.
        $this->assertIsFloat($listing->latitude);
        $this->assertIsFloat($listing->longitude);

        // Exact round-trip — no trimming inside the (10,7) precision envelope.
        $this->assertEqualsWithDelta(8.4542123,   $listing->latitude,  0.0000001);
        $this->assertEqualsWithDelta(124.6411567, $listing->longitude, 0.0000001);
    }

    public function test_it_allows_null_lat_lng(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'latitude'  => null,
            'longitude' => null,
        ]));

        $listing = Listing::first();
        $this->assertNull($listing->latitude);
        $this->assertNull($listing->longitude);
    }

    // =========================================================================
    // Photos (5)
    // =========================================================================

    public function test_it_creates_listing_images_in_order_with_correct_sort_order(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [
                $this->seedTmpPhoto('photo-a'),
                $this->seedTmpPhoto('photo-b'),
                $this->seedTmpPhoto('photo-c'),
            ],
        ]));

        $listing = Listing::first();
        $images = $listing->images()->orderBy('sort_order')->get();

        $this->assertCount(3, $images);
        $this->assertSame(0, $images[0]->sort_order);
        $this->assertSame(1, $images[1]->sort_order);
        $this->assertSame(2, $images[2]->sort_order);
    }

    public function test_it_sets_display_image_id_to_first_photo(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [
                $this->seedTmpPhoto('photo-a'),
                $this->seedTmpPhoto('photo-b'),
                $this->seedTmpPhoto('photo-c'),
            ],
        ]));

        $listing = Listing::first()->refresh();
        $firstImage = $listing->images()->orderBy('sort_order')->first();

        $this->assertSame($firstImage->id, $listing->display_image_id);
    }

    public function test_it_copies_photos_from_tmp_to_listings_uuid_path_on_s3(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [
                $this->seedTmpPhoto('photo-a'),
                $this->seedTmpPhoto('photo-b'),
            ],
        ]));

        $listing = Listing::first();

        Storage::disk('s3')->assertExists("listings/{$listing->uuid}/photo-a");
        Storage::disk('s3')->assertExists("listings/{$listing->uuid}/photo-b");
    }

    public function test_it_deletes_tmp_originals_after_successful_copy(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [
                $this->seedTmpPhoto('photo-a'),
                $this->seedTmpPhoto('photo-b'),
            ],
        ]));

        Storage::disk('s3')->assertMissing('tmp/photo-a');
        Storage::disk('s3')->assertMissing('tmp/photo-b');
    }

    public function test_it_stores_permanent_s3_url_in_listing_images_url_column(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'photos' => [$this->seedTmpPhoto('photo-a')],
        ]));

        $listing = Listing::first();
        $image = $listing->images()->first();

        $this->assertStringContainsString("listings/{$listing->uuid}/photo-a", $image->url);
    }

    // =========================================================================
    // Amenities (2)
    // =========================================================================

    public function test_it_syncs_selected_amenities_to_pivot(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $a1 = Amenity::factory()->create();
        $a2 = Amenity::factory()->create();

        $this->post(route('admin.listings.store'), $this->validPayload([
            'amenities' => [$a1->id, $a2->id],
        ]));

        $listing = Listing::first();
        $attached = $listing->amenities()->pluck('amenities.id')->sort()->values()->all();

        $this->assertSame([$a1->id, $a2->id], $attached);
    }

    public function test_it_allows_zero_amenities(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'amenities' => [],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Listing::count());
        $this->assertSame(0, Listing::first()->amenities()->count());
    }

    // =========================================================================
    // Redirect intent (3) — always lands on unverified per FRD-023
    // =========================================================================

    public function test_it_redirects_to_unverified_listings_with_publish_intent(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'intent' => 'publish',
        ]))->assertRedirect(route('admin.unverified-listings.index'));
    }

    public function test_it_redirects_to_empty_form_with_publish_and_add_another_intent(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'intent' => 'publish-and-add-another',
        ]))->assertRedirect(route('admin.listings.create'));
    }

    public function test_it_flashes_success_message(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'title' => 'Pueblo Pad',
        ]))->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Pueblo Pad'));
    }

    // =========================================================================
    // Queue defaults (2) — FRD-023
    // =========================================================================

    public function test_it_defaults_prequal_status_to_not_called(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload());

        $this->assertSame('not_called', Listing::first()->prequal_status);
    }

    public function test_it_defaults_queue_status_to_unassigned(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload());

        $this->assertSame('unassigned', Listing::first()->queue_status);
    }

    // =========================================================================
    // is_verified hardcoded server-side (1) — FRD-023 security boundary
    // =========================================================================

    public function test_it_hardcodes_is_verified_to_false_regardless_of_payload(): void
    {
        // The form drops the field entirely, but a determined attacker could
        // curl POST with is_verified=1. Server must ignore it and persist false.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'is_verified' => true,
        ]))->assertSessionHasNoErrors();

        $listing = Listing::first();
        $this->assertFalse((bool) $listing->is_verified);
        $this->assertNull($listing->verified_at);
    }

    public function test_it_hardcodes_is_featured_to_false_regardless_of_payload(): void
    {
        // is_featured is moderation/curation state, not a desk-create choice.
        // Same security boundary as is_verified — server ignores submitted value.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'is_featured' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertFalse((bool) Listing::first()->is_featured);
    }

    // =========================================================================
    // Transactional integrity (1)
    // =========================================================================

    public function test_it_rolls_back_when_s3_copy_fails(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Submit a payload where the photo's tmp/ key references a file
        // that does NOT exist on the fake S3 disk. Storage::copy() throws,
        // the transaction rolls back, no Listing/ListingImage rows persist.
        $payload = $this->validPayload([
            'photos' => [[
                'name' => 'ghost.jpg',
                'size' => 999,
                'key'  => 'tmp/ghost-key-that-does-not-exist',
            ]],
        ]);

        try {
            $this->post(route('admin.listings.store'), $payload);
        } catch (\Throwable $e) {
            // Swallow — we expect a 500 from the S3 copy failure
        }

        $this->assertSame(0, Listing::count(), 'No Listing rows should persist after rollback');
        $this->assertSame(0, ListingImage::count(), 'No ListingImage rows should persist after rollback');
    }

    // =========================================================================
    // Lifecycle audit log (1)
    // =========================================================================

    // =========================================================================
    // Validation — contact_phone (required + PH mobile normalization to E.164)
    // =========================================================================

    public function test_it_rejects_when_contact_phone_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $payload = $this->validPayload();
        unset($payload['contact_phone']);

        $this->post(route('admin.listings.store'), $payload)
             ->assertSessionHasErrors(['contact_phone' => 'Contact phone is required.']);
    }

    public function test_it_rejects_when_contact_phone_empty_string(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '',
        ]))->assertSessionHasErrors(['contact_phone' => 'Contact phone is required.']);
    }

    public function test_it_normalizes_local_leading_zero_to_e164(): void
    {
        // 09171234567 -> +639171234567
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '09171234567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', Listing::first()->contact_phone);
    }

    public function test_it_normalizes_no_country_code_to_e164(): void
    {
        // 9171234567 -> +639171234567
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '9171234567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', Listing::first()->contact_phone);
    }

    public function test_it_accepts_already_e164_format(): void
    {
        // +639171234567 -> +639171234567 (canonical, persisted as-is)
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '+639171234567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', Listing::first()->contact_phone);
    }

    public function test_it_normalizes_international_without_plus_to_e164(): void
    {
        // 639171234567 -> +639171234567
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '639171234567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', Listing::first()->contact_phone);
    }

    public function test_it_strips_spaces_and_dashes_before_validation(): void
    {
        // "+63-917 123-4567" -> +639171234567. Admins should be able to paste
        // any common format and have it normalized.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '+63-917 123-4567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', Listing::first()->contact_phone);
    }

    public function test_it_rejects_phone_too_short(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '09171234',
        ]))->assertSessionHasErrors(['contact_phone' => 'Invalid PH mobile number.']);
    }

    public function test_it_rejects_phone_too_long(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '091712345678',  // 12 digits
        ]))->assertSessionHasErrors(['contact_phone' => 'Invalid PH mobile number.']);
    }

    public function test_it_rejects_phone_with_letters(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '0917abc4567',
        ]))->assertSessionHasErrors(['contact_phone' => 'Invalid PH mobile number.']);
    }

    public function test_it_rejects_landline_number(): void
    {
        // PH mobile is exclusively the 9XX prefix range. Landlines (e.g. CDO
        // 088-XXXXXX) aren't SMS-reachable, so reject for the calls-team flow.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '088123456',
        ]))->assertSessionHasErrors(['contact_phone' => 'Invalid PH mobile number.']);
    }

    public function test_it_rejects_foreign_number(): void
    {
        // +1 (US) — not PH; reject. Calls team is PH-only.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'contact_phone' => '+14155551234',
        ]))->assertSessionHasErrors(['contact_phone' => 'Invalid PH mobile number.']);
    }

    // =========================================================================
    // Lifecycle audit log (1)
    // =========================================================================

    public function test_it_writes_a_created_lifecycle_event_after_storing_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.listings.store'), $this->validPayload([
            'title' => 'Modern 2-BR in Pueblo de Oro',
        ]));

        $listing = Listing::sole();

        $events = ListingLifecycleEvent::where('listing_id', $listing->id)->get();
        $this->assertCount(1, $events, 'Exactly one lifecycle event must land per store call');

        $event = $events->first();
        $this->assertSame('created', $event->event_type);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertNull($event->reason, 'Reason is null for created/updated events');

        // notes is JSON-encoded request payload, with framework keys stripped.
        $this->assertNotNull($event->notes);
        $notes = json_decode($event->notes, true);
        $this->assertIsArray($notes, 'notes must be valid JSON');
        $this->assertSame('Modern 2-BR in Pueblo de Oro', $notes['title']);
        $this->assertArrayNotHasKey('_token', $notes);
        $this->assertArrayNotHasKey('_method', $notes);
    }
}
