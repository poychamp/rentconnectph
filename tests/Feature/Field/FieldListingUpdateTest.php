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
                'contact_phone'      => '+639171234567',
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
            ->assertRedirect(route('admin.login'));
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
    // verification_notes, prequal_status. The form holds copies of these for
    // read-only display, so a tampered submit could include them.
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
                'verification_notes' => 'Hijacked notes',
                'prequal_status'     => PrequalStatus::notCalled()->value,
            ])
        );

        $listing->refresh();
        $this->assertSame('+639171234567',                   $listing->contact_phone);
        $this->assertSame(ContactType::owner()->value,       $listing->contact_type);
        $this->assertSame(SourceSite::olx()->value,          $listing->source_site);
        $this->assertSame('https://olx.ph/original',         $listing->source_url);
        $this->assertSame('Calls-team set this.',            $listing->verification_notes);
        $this->assertSame(PrequalStatus::calledYes()->value, $listing->prequal_status);
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
}
