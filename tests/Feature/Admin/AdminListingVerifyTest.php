<?php

namespace Tests\Feature\Admin;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingVerifyTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    private function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    private function visitedListing(array $overrides = []): Listing
    {
        $field = User::factory()->field()->create();
        return Listing::factory()
            ->withImages(2)
            ->create(array_merge([
                'is_verified'        => false,
                'verified_at'        => null,
                'listed_at'          => null,
                'queue_status'       => QueueStatus::visited()->value,
                'visited_at'         => Carbon::parse('2026-04-30 14:00:00'),
                'assigned_to'        => $field->id,
                'assigned_at'        => Carbon::parse('2026-04-29 10:00:00'),
                'is_featured'        => false,
                'title'              => 'Apartment near Capitol',
                'description'        => 'Quiet street.',
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
                'verification_notes' => 'Owner spoke clearly.',
            ], $overrides))
            ->fresh(['images']);
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
        return array_merge([
            'title'         => $listing->title,
            'description'   => $listing->description ?? 'A description.',
            'listing_type'  => $listing->type ?? ListingType::apartment()->value,
            'price_monthly' => $listing->price_monthly ?? 12500,
            'barangay'      => $listing->barangay ?? Barangay::lapasan()->value,
            'beds'          => $listing->beds ?? 2,
            'baths'         => $listing->baths ?? 1,
            'sqm'           => $listing->sqm ?? 38,
            'latitude'      => $listing->latitude ?? 8.4831,
            'longitude'     => $listing->longitude ?? 124.6505,
            'directions'    => $listing->directions ?? 'Past the green gate.',
            'amenities'     => [],
            'photos'        => $this->existingPhotos($listing),
            'is_featured'   => false,
        ], $overrides);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->visitedListing();

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $listing = $this->visitedListing();

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing))
            ->assertForbidden();
    }

    public function test_it_returns_422_when_listing_queue_status_is_not_visited(): void
    {
        $this->asAdmin();

        foreach ([QueueStatus::assigned(), QueueStatus::unassigned(), QueueStatus::dead()] as $status) {
            $listing = $this->visitedListing(['queue_status' => $status->value]);
            $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing))
                ->assertSessionHasErrors(['listing'], null, 'verify');

            $this->assertFalse($listing->fresh()->is_verified);
        }
    }

    public function test_it_returns_422_when_listing_is_already_verified(): void
    {
        // Concurrent verify case — second admin lands on already-verified listing.
        $this->asAdmin();
        $listing = $this->visitedListing([
            'is_verified' => true,
            'verified_at' => Carbon::parse('2026-04-30 16:00:00'),
        ]);

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing))
            ->assertSessionHasErrors(['listing'], null, 'verify');

        // verified_at should NOT be overwritten by the concurrent request.
        $this->assertEquals(
            Carbon::parse('2026-04-30 16:00:00'),
            $listing->fresh()->verified_at,
        );
    }

    public function test_it_validates_required_fields_for_publishable_listing(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        // Each required field, individually empty → 422.
        // Nullable on this slice (admin can verify a partial listing): description,
        // beds, baths, sqm, amenities. Renter-essential fields stay required.
        $required = [
            'title'         => '',
            'listing_type'  => '',
            'price_monthly' => null,
            'barangay'      => '',
            'latitude'      => null,
            'longitude'     => null,
            'directions'    => '',
            'photos'        => [],
        ];

        foreach ($required as $field => $emptyValue) {
            $this->put(
                route('admin.listings.verify', $listing->uuid),
                $this->validPayload($listing, [$field => $emptyValue]),
            )->assertSessionHasErrors([$field], null, 'verify');
        }
    }

    public function test_it_persists_editable_field_changes_on_verify(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'title'         => 'Tweaked Title',
                'description'   => 'Tweaked description.',
                'price_monthly' => 14000,
                'beds'          => 3,
            ]),
        );

        $fresh = $listing->fresh();
        $this->assertSame('Tweaked Title', $fresh->title);
        $this->assertSame('Tweaked description.', $fresh->description);
        $this->assertSame(14000, $fresh->price_monthly);
        $this->assertSame(3, $fresh->beds);
    }

    public function test_it_flips_is_verified_to_true_and_sets_verified_at_to_now(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        Carbon::setTestNow(Carbon::parse('2026-05-15 09:30:00'));

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing));

        $fresh = $listing->fresh();
        $this->assertTrue($fresh->is_verified);
        $this->assertNotNull($fresh->verified_at);
        $this->assertTrue($fresh->verified_at->equalTo(Carbon::parse('2026-05-15 09:30:00')));

        Carbon::setTestNow();
    }

    public function test_it_sets_listed_at_to_the_same_value_as_verified_at_on_verify(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        Carbon::setTestNow(Carbon::parse('2026-05-15 09:30:00'));

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing));

        $fresh = $listing->fresh();
        $this->assertNotNull($fresh->listed_at);
        $this->assertTrue($fresh->listed_at->equalTo(Carbon::parse('2026-05-15 09:30:00')));
        $this->assertTrue(
            $fresh->listed_at->equalTo($fresh->verified_at),
            'listed_at must equal verified_at on first verify'
        );

        Carbon::setTestNow();
    }

    public function test_it_preserves_immutable_fields(): void
    {
        // queue_status, visited_at, assigned_to, assigned_at — the field officer's
        // attribution + visit timeline must survive verification untouched.
        $this->asAdmin();
        $listing = $this->visitedListing();
        $originalAssignedTo = $listing->assigned_to;
        $originalAssignedAt = $listing->assigned_at;
        $originalVisitedAt  = $listing->visited_at;

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing));

        $fresh = $listing->fresh();
        $this->assertSame(QueueStatus::visited()->value, $fresh->queue_status);
        $this->assertSame($originalAssignedTo, $fresh->assigned_to);
        $this->assertEquals($originalAssignedAt, $fresh->assigned_at);
        $this->assertEquals($originalVisitedAt, $fresh->visited_at);
    }

    public function test_it_silently_ignores_calls_team_locked_fields(): void
    {
        // Admin tampers via raw PUT with calls-team-captured + field-officer-captured
        // fields — server keeps the persisted values. Defense-in-depth: verify-edit
        // UI marks these read-only, and the controller backs that up server-side.
        // (verification_notes is now admin-overridable per the verify-edit workflow.)
        $this->asAdmin();
        $listing = $this->visitedListing();
        $originalContactPhone = $listing->contact_phone;
        $originalContactType  = $listing->contact_type;
        $originalSourceSite   = $listing->source_site;
        $originalSourceUrl    = $listing->source_url;

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'contact_phone' => '+639999999999',
                'contact_type'  => ContactType::caretaker()->value,
                'source_site'   => SourceSite::olx()->value,
                'source_url'    => 'https://attacker.example/tampered',
            ]),
        );

        $fresh = $listing->fresh();
        $this->assertSame($originalContactPhone, $fresh->contact_phone);
        $this->assertSame($originalContactType, $fresh->contact_type);
        $this->assertSame($originalSourceSite, $fresh->source_site);
        $this->assertSame($originalSourceUrl, $fresh->source_url);
    }

    public function test_it_allows_admin_to_override_verification_notes_on_verify(): void
    {
        // verification_notes was historically calls-team-captured (and previously
        // editable only by the field officer). Admin can now finalize/correct it
        // during the verify step — clarifying ambiguous notes, adding broker-side
        // context the field officer didn't capture, etc.
        $this->asAdmin();
        $listing = $this->visitedListing();

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'verification_notes' => 'Verified via call-back; owner confirmed asking rate. Broker fee split 50/50.',
            ]),
        );

        $listing->refresh();
        $this->assertSame(
            'Verified via call-back; owner confirmed asking rate. Broker fee split 50/50.',
            $listing->verification_notes,
            'Admin must be able to override verification_notes during verify-edit.',
        );
    }

    public function test_it_persists_is_featured_when_admin_toggles_it_on(): void
    {
        // Per FRD-032 + the user's "verify-and-feature in one shot" pin —
        // admin can flip is_featured during verification without bouncing to
        // /admin/featured-listings. featured_order stays null; the featured
        // page's self-heal-on-read assigns a slot on next render.
        $this->asAdmin();
        $listing = $this->visitedListing(['is_featured' => false]);

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, ['is_featured' => true]),
        );

        $this->assertTrue($listing->fresh()->is_featured);
    }

    public function test_it_records_an_updated_lifecycle_event_with_admin_as_actor(): void
    {
        // Mirrors the field-side request-verification flow which also writes
        // event_type='updated'. Single event type for "this row was mutated";
        // discriminate verify vs other updates via actor + state diff if needed.
        $admin = $this->asAdmin();
        $listing = $this->visitedListing();

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing));

        $event = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('updated', $event->event_type);
        $this->assertSame($admin->id, $event->actor_id);
    }

    public function test_it_redirects_to_visited_listings_index_on_success(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        $this->put(route('admin.listings.verify', $listing->uuid), $this->validPayload($listing))
            ->assertRedirect(route('admin.visited-listings.index'));
    }

    public function test_it_validates_field_format_constraints(): void
    {
        // Format/range/enum constraints beyond the basic required check. One test,
        // many sub-cases via foreach — each invalid value, individually, → 422.
        $this->asAdmin();
        $listing = $this->visitedListing();

        $invalid = [
            // Length caps
            'title'              => str_repeat('a', 201),
            'description'        => str_repeat('a', 5001),
            'directions'         => str_repeat('a', 501),
            'verification_notes' => str_repeat('a', 2001),
            // Enum allowlist
            'listing_type'  => 'not-a-valid-type',
            'barangay'      => 'not-a-valid-barangay',
            // Numeric range
            'price_monthly' => 0,
            'beds'          => 21,
            'baths'         => 21,
            'sqm'           => -1,
            'latitude'      => 91,
            'longitude'     => 181,
        ];

        foreach ($invalid as $field => $invalidValue) {
            $this->put(
                route('admin.listings.verify', $listing->uuid),
                $this->validPayload($listing, [$field => $invalidValue]),
            )->assertSessionHasErrors([$field], null, 'verify');
        }
    }

    public function test_it_rejects_amenity_id_not_in_amenities_table(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, ['amenities' => [99999]]),
        )->assertSessionHasErrors(['amenities.0'], null, 'verify');
    }

    public function test_it_persists_photo_sort_order_matching_submitted_array_order(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();
        $images = $listing->images->sortBy('sort_order')->values();
        $first  = $images[0];
        $second = $images[1];

        // Submit with photos in REVERSED order — server should persist sort_order
        // matching the new order, NOT the existing order.
        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $second->id],
                    ['existing_id' => $first->id],
                ],
            ]),
        );

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_it_deletes_photos_not_in_submitted_array(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();
        $images = $listing->images->sortBy('sort_order')->values();
        $kept    = $images[0];
        $removed = $images[1];

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [['existing_id' => $kept->id]],
            ]),
        );

        // ListingImage uses SoftDeletes; ->fresh() bypasses scopes via
        // newQueryWithoutScopes(). Use find() so the SoftDeletes scope filters
        // the trashed row out.
        $this->assertNotNull(ListingImage::find($kept->id));
        $this->assertNull(ListingImage::find($removed->id));
    }

    public function test_it_keeps_existing_photos_and_persists_new_tmp_uploads(): void
    {
        $this->asAdmin();
        $listing = $this->visitedListing();
        $existing = $listing->images->sortBy('sort_order')->first();

        // Seed a tmp/ photo on the fake S3 disk.
        Storage::disk('s3')->put('tmp/new-photo.jpg', 'fake-image-bytes');

        $this->put(
            route('admin.listings.verify', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $existing->id],
                    ['key' => 'tmp/new-photo.jpg', 'name' => 'new-photo.jpg', 'size' => 12345],
                ],
            ]),
        );

        $fresh = $listing->fresh(['images']);
        $this->assertCount(2, $fresh->images);

        // Existing kept (sort_order=0).
        $existingFresh = $fresh->images->firstWhere('id', $existing->id);
        $this->assertNotNull($existingFresh);
        $this->assertSame(0, $existingFresh->sort_order);

        // New photo promoted to permanent S3 (no longer at tmp/) and ListingImage row created.
        $newImage = $fresh->images->firstWhere('id', '!=', $existing->id);
        $this->assertNotNull($newImage);
        $this->assertSame(1, $newImage->sort_order);
        $this->assertStringNotContainsString('tmp/', $newImage->url);
        $this->assertStringContainsString("listings/{$fresh->uuid}/", $newImage->url);
    }
}
