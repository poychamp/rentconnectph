<?php

namespace Tests\Feature\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\PrequalStatus;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class FieldListingUpdateTest extends TestCase
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

    private function assignedListingFor(User $officer, array $overrides = []): Listing
    {
        return Listing::factory()
            ->withImages(1)
            ->create(array_merge([
                'is_verified'        => false,
                'verified_at'        => null,
                'queue_status'       => QueueStatus::assigned()->value,
                'assigned_to'        => $officer->id,
                'assigned_at'        => Carbon::parse('2026-04-20 10:00:00'),
                'prequal_status'     => PrequalStatus::calledYes()->value,
                'listing_contact_id' => ListingContact::factory()->create([
                    'phone' => '+639171234567',
                    'name'  => 'Maria Reyes',
                    'notes' => 'Calls-team contact',
                ])->id,
                'contact_type'       => ContactType::owner()->value,
                'source_site'        => SourceSite::olx()->value,
                'source_url'         => 'https://olx.ph/original',
                'verification_notes' => 'Calls-team set this.',
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
            'title'         => 'Updated by Field Officer',
            'description'   => 'Saw the front gate, looks good.',
            'listing_type'  => ListingType::apartment()->value,
            'price_monthly' => 12500,
            'barangay'      => Barangay::lapasan()->value,
            'beds'          => 2,
            'baths'         => 1,
            'sqm'           => 38,
            'latitude'      => 8.4831,
            'longitude'     => 124.6505,
            'directions'    => 'Past the green gate.',
            'amenities'     => [],
            'photos'        => $this->existingPhotos($listing),
        ], $overrides);
    }

    // =========================================================================
    // Auth + slice guards
    // =========================================================================

    public function test_it_redirects_guest_to_login(): void
    {
        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_listings_field_work_permission(): void
    {
        $orphan = User::factory()->create();
        $this->actingAs($orphan, 'admin');

        $marco = User::factory()->field()->create();
        $listing = $this->assignedListingFor($marco);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertForbidden();
    }

    public function test_it_returns_404_when_listing_is_assigned_to_a_different_officer(): void
    {
        $this->asMarco();
        $carlo = User::factory()->field()->create();
        $listing = $this->assignedListingFor($carlo);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertNotFound();
    }

    public function test_it_returns_404_when_queue_status_is_not_assigned(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'queue_status' => QueueStatus::unassigned()->value,
        ]);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertNotFound();
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_it_requires_title(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['title' => ''])
        )->assertSessionHasErrors(['title' => 'Title is required.']);
    }

    public function test_it_rejects_title_longer_than_200_characters(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['title' => str_repeat('a', 201)])
        )->assertSessionHasErrors(['title' => 'Title is too long (max 200 characters).']);
    }

    public function test_it_requires_directions(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['directions' => ''])
        )->assertSessionHasErrors(['directions' => 'Directions are required.']);
    }

    public function test_it_rejects_invalid_listing_type(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['listing_type' => 'mansion'])
        )->assertSessionHasErrors(['listing_type']);
    }

    public function test_it_rejects_invalid_barangay(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['barangay' => 'not_a_real_barangay'])
        )->assertSessionHasErrors(['barangay']);
    }

    public function test_it_rejects_out_of_bounds_latitude_and_longitude(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['latitude' => 95, 'longitude' => -181])
        )->assertSessionHasErrors(['latitude', 'longitude']);
    }

    // =========================================================================
    // Persistence — editable fields
    // =========================================================================

    public function test_it_persists_editable_scalar_fields(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco, [
            'title'         => 'Original Title',
            'price_monthly' => 9999,
            'beds'          => 1,
        ]);

        $this->put(
            route('field.listings.update', $listing->uuid),
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

    public function test_it_syncs_amenities(): void
    {
        $marco = $this->asMarco();
        $wifi   = Amenity::firstOrCreate(['slug' => 'wifi'],   ['name' => 'Wi-Fi',   'icon' => 'wifi',   'sort_order' => 1]);
        $aircon = Amenity::firstOrCreate(['slug' => 'aircon'], ['name' => 'Air-con', 'icon' => 'aircon', 'sort_order' => 2]);

        $listing = $this->assignedListingFor($marco);
        $listing->amenities()->sync([$wifi->id]);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, ['amenities' => [$aircon->id]])
        );

        $listing->refresh();
        $this->assertEqualsCanonicalizing(
            [$aircon->id],
            $listing->amenities->pluck('id')->all()
        );
    }

    public function test_it_persists_photo_sort_order_matching_the_submitted_array_order(): void
    {
        $marco = $this->asMarco();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => QueueStatus::assigned()->value,
                'assigned_to'  => $marco->id,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $img1 = $existing[0];
        $img2 = $existing[1];
        $img3 = $existing[2];

        Storage::disk('s3')->put('tmp/new-photo.jpg', 'fake-bytes');
        $newPhoto = ['key' => 'tmp/new-photo.jpg', 'name' => 'new.jpg', 'size' => 9];

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    $newPhoto,
                    ['existing_id' => $img3->id],
                    ['existing_id' => $img2->id],
                    ['existing_id' => $img1->id],
                ],
            ]),
        );

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
        $marco = $this->asMarco();
        $listing = Listing::factory()
            ->withImages(3)
            ->create([
                'is_verified'  => false,
                'verified_at'  => null,
                'queue_status' => QueueStatus::assigned()->value,
                'assigned_to'  => $marco->id,
            ])
            ->fresh(['images']);

        $existing = $listing->images->sortBy('id')->values();
        $kept     = $existing[0];
        $removedA = $existing[1];
        $removedB = $existing[2];

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $kept->id],
                ],
            ]),
        );

        $listing->refresh()->load('images');

        $this->assertCount(1, $listing->images);
        $this->assertSame($kept->id, $listing->images->first()->id);
        $this->assertNull(\App\Models\ListingImage::find($removedA->id));
        $this->assertNull(\App\Models\ListingImage::find($removedB->id));
    }

    public function test_it_keeps_existing_photos_and_persists_new_tmp_uploads(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);
        $existing = $listing->images->first();

        Storage::disk('s3')->put('tmp/new-photo.jpg', 'fake-bytes');

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'photos' => [
                    ['existing_id' => $existing->id],
                    ['key' => 'tmp/new-photo.jpg', 'name' => 'new-photo.jpg', 'size' => 9],
                ],
            ])
        );

        $listing->refresh();
        $this->assertCount(2, $listing->images);
        $this->assertContains($existing->id, $listing->images->pluck('id')->all());
    }

    // =========================================================================
    // Security boundary — calls-team-locked fields are silently ignored.
    // Calls-team owns contact_phone, contact_type, source_site, source_url,
    // prequal_status. The form holds copies of these for read-only display,
    // so a tampered submit could include them. (verification_notes is now
    // field-officer-editable per the on-site verification workflow.)
    // =========================================================================

    public function test_it_silently_ignores_calls_team_locked_fields_in_the_request(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'contact_phone'      => '+639170000000',
                'contact_type'       => ContactType::authorizedRep()->value,
                'source_site'        => SourceSite::facebookGroup()->value,
                'source_url'         => 'https://attacker.example/owned',
                'prequal_status'     => PrequalStatus::notCalled()->value,
            ])
        );

        $listing->refresh();
        $this->assertSame('+639171234567',                   $listing->listingContact->phone);
        $this->assertSame(ContactType::owner()->value,       $listing->contact_type);
        $this->assertSame(SourceSite::olx()->value,          $listing->source_site);
        $this->assertSame('https://olx.ph/original',         $listing->source_url);
        $this->assertSame(PrequalStatus::calledYes()->value, $listing->prequal_status);
    }

    public function test_it_silently_ignores_contact_visibility_flags_in_the_request(): void
    {
        // is_show_name + is_show_notes are admin-only — field officers can't flip
        // them. Sent via either shape (flat or nested under contact) should be
        // silently dropped by the field controller's validated-allowlist pattern.
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        // Sanity: factory-created contact has both flags at DB default (true).
        $this->assertTrue($listing->listingContact->is_show_name);
        $this->assertTrue($listing->listingContact->is_show_notes);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                // Flat shape attacker payload
                'is_show_name'  => false,
                'is_show_notes' => false,
                // Nested-under-contact attacker payload
                'contact' => [
                    'is_show_name'  => false,
                    'is_show_notes' => false,
                ],
            ])
        )->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertTrue($listing->listingContact->is_show_name);
        $this->assertTrue($listing->listingContact->is_show_notes);
    }

    // =========================================================================
    // Field-officer-editable carve-out — verification_notes was historically
    // calls-team-locked but is now overridable by the field officer because
    // the on-site visit reveals discrepancies the calls team couldn't know
    // (gate not matching the photos, owner correcting details verbally,
    // amenities present that weren't listed, etc.).
    // =========================================================================

    public function test_it_allows_field_officer_to_override_verification_notes(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'verification_notes' => 'Visited Tue 10am — gate matches photos; owner confirmed +63917…; aircon present (not listed).',
            ])
        );

        $listing->refresh();
        $this->assertSame(
            'Visited Tue 10am — gate matches photos; owner confirmed +63917…; aircon present (not listed).',
            $listing->verification_notes,
            'Field officer must be able to override calls-team verification_notes during on-site edit.',
        );
    }

    public function test_it_rejects_verification_notes_longer_than_2000_characters(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'verification_notes' => str_repeat('x', 2001),
            ])
        )->assertSessionHasErrors([
            'verification_notes' => 'Verification notes must be 2000 characters or fewer.',
        ]);

        $listing->refresh();
        $this->assertSame(
            'Calls-team set this.',
            $listing->verification_notes,
            'verification_notes must be unchanged when validation rejects the payload.',
        );
    }

    // =========================================================================
    // Security boundary — admin-owned queue/moderation fields are silently
    // ignored. assigned_to / assigned_at / queue_status mutate only via the
    // admin's unverifiedUpdate flow (calls team assigning). is_verified /
    // verified_at flip only via admin verification. A field officer must not
    // be able to reassign themselves, time-bend assigned_at, advance
    // queue_status, or self-publish via crafted payloads.
    // =========================================================================

    public function test_it_silently_ignores_admin_owned_assignment_and_moderation_fields(): void
    {
        $marco = $this->asMarco();
        $assignedAt = Carbon::parse('2026-04-20 10:00:00');
        $listing = $this->assignedListingFor($marco, [
            'assigned_at' => $assignedAt,
        ]);

        $other = User::factory()->field()->create();

        $this->put(
            route('field.listings.update', $listing->uuid),
            $this->validPayload($listing, [
                'assigned_to'  => $other->id,
                'assigned_at'  => Carbon::parse('2030-01-01'),
                'queue_status' => QueueStatus::unassigned()->value,
                'is_verified'  => true,
                'verified_at'  => Carbon::parse('2030-01-01'),
            ])
        );

        $listing->refresh();
        $this->assertSame($marco->id,                     $listing->assigned_to);
        $this->assertEquals($assignedAt,                  $listing->assigned_at);
        $this->assertSame(QueueStatus::assigned()->value, $listing->queue_status);
        $this->assertFalse((bool) $listing->is_verified);
        $this->assertNull($listing->verified_at);
    }

    // =========================================================================
    // Lifecycle event — every save records who-edited-what-when. Audit trail.
    // queue_status doesn't change, but the event is the project's append-only
    // record of activity per listing (matches admin's unverifiedUpdate
    // pattern, event_type='updated' regardless of actor role).
    // =========================================================================

    public function test_it_records_an_updated_lifecycle_event_with_the_field_officer_as_actor(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing));

        $event = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('updated', $event->event_type);
        $this->assertSame($marco->id, $event->actor_id);
    }

    // =========================================================================
    // Redirect on success
    // =========================================================================

    public function test_it_redirects_to_field_listings_index_on_success(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(route('field.listings.update', $listing->uuid), $this->validPayload($listing))
            ->assertRedirect(route('field.listings.index'));
    }

    public function test_it_redirects_to_field_priority_index_when_from_is_priority(): void
    {
        $marco = $this->asMarco();
        $listing = $this->assignedListingFor($marco);

        $this->put(
            route('field.listings.update', $listing->uuid) . '?from=priority',
            $this->validPayload($listing),
        )->assertRedirect(route('field.priority.index'));
    }
}
