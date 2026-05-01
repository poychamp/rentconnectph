<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

/**
 * Covers the prequal_status='not_called' slice of the unverified-update
 * endpoint. Admin is updating basic lead details before any call has been
 * made — only `title` and `contact_phone` are required; everything else
 * is nullable. The called_yes / no_answer slices are separate iterations.
 */
class AdminListingUnverifiedUpdateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    protected function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    protected function makeFieldUser(string $name = 'Field Officer'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('field');
        return $user;
    }

    protected function makeUnverifiedListing(int $imageCount = 2, array $state = []): Listing
    {
        return Listing::factory()
            ->withImages($imageCount)
            ->state(array_merge([
                'is_verified'    => false,
                'verified_at'    => null,
                'prequal_status' => 'not_called',
                'queue_status'   => 'unassigned',
                'contact_phone'  => '+639171234567',
            ], $state))
            ->create()
            ->fresh(['images', 'amenities']);
    }

    protected function seedTmpPhoto(string $key): array
    {
        Storage::disk('s3')->put("tmp/{$key}", 'fake-image-bytes');
        return ['name' => "{$key}.jpg", 'size' => 12345, 'key' => "tmp/{$key}"];
    }

    protected function existingPhotos(Listing $listing): array
    {
        return $listing->images
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($img) => ['existing_id' => $img->id])
            ->all();
    }

    /**
     * Minimal payload for the not_called slice — only title and contact_phone
     * are hard-required by the controller; the rest mirrors what the form
     * sends when admin is updating basic lead details before calling.
     */
    protected function notCalledPayload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'title'          => 'Updated Lead Title',
            'contact_phone'  => '+639171234567',
            'prequal_status' => 'not_called',
            'photos'         => $this->existingPhotos($listing),
        ], $overrides);
    }

    /**
     * Valid payload for the called_yes slice — directions and contact_type
     * are required in addition to the not_called base; assigned_to and
     * verification_notes stay nullable so admin can save mid-call before
     * deciding on a field officer.
     */
    protected function calledYesPayload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'title'              => 'Updated Lead Title',
            'contact_phone'      => '+639171234567',
            'prequal_status'     => 'called_yes',
            'photos'             => $this->existingPhotos($listing),
            'directions'         => 'Past the gate, second left.',
            'contact_type'       => 'owner',
            'verification_notes' => null,
            'assigned_to'        => null,
        ], $overrides);
    }

    // =========================================================================
    // Auth + access (2)
    // =========================================================================

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->makeUnverifiedListing();

        $this->put(route('admin.listings.unverified-update', $listing->uuid), $this->notCalledPayload($listing))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_404_for_unknown_or_soft_deleted_uuid(): void
    {
        $this->asAdmin();

        $this->put(route('admin.listings.unverified-update', '00000000-0000-0000-0000-000000000000'), [])
            ->assertNotFound();

        $listing = $this->makeUnverifiedListing();
        $listing->delete();
        $this->put(route('admin.listings.unverified-update', $listing->uuid), $this->notCalledPayload($listing))
            ->assertNotFound();
    }

    // =========================================================================
    // Slice guard — PUT against a verified listing's unverified-update URL
    // returns 404 (mirrors the GET unverifiedEdit slice guard).
    // =========================================================================

    public function test_it_returns_404_when_listing_is_verified(): void
    {
        $this->asAdmin();
        $verified = Listing::factory()->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $this->put(
            route('admin.listings.unverified-update', $verified->uuid),
            $this->notCalledPayload($verified)
        )->assertNotFound();
    }

    // =========================================================================
    // Required fields when not_called: title + contact_phone only. Listing
    // specs (beds/baths/sqm/listing_type/barangay/price_monthly), source
    // fields, photos, and call-context fields are all nullable in this state
    // — admin is just updating partial lead details before calling.
    // =========================================================================

    public function test_it_requires_only_title_and_contact_phone_when_prequal_status_is_not_called(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(route('admin.listings.unverified-update', $listing->uuid), [
            'title'          => '',
            'contact_phone'  => '',
            'prequal_status' => 'not_called',
            'photos'         => [],
            // Everything else omitted on purpose — must be nullable here.
        ]);

        $response->assertSessionHasErrors([
            'title'         => 'Title is required.',
            'contact_phone' => 'Contact phone is required.',
        ]);

        $response->assertSessionDoesntHaveErrors([
            'listing_type', 'barangay', 'beds', 'baths', 'sqm', 'price_monthly',
            'source_site', 'source_url', 'photos',
        ]);
    }

    // =========================================================================
    // Call-context fields (directions/contact_type/verification_notes/
    // assigned_to) belong to the called_yes flow. When prequal_status is
    // 'not_called', the controller must NOT validate or persist them — even
    // if the payload contains values, they're silently dropped. The Call
    // Context card in the UI doesn't exist at this stage, but a stale form
    // submit (or attacker payload) could include them.
    // =========================================================================

    public function test_it_silently_ignores_call_context_fields_when_prequal_status_is_not_called(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, [
                'directions'         => 'Past the gate, second left.',
                'contact_type'       => 'authorized_rep',
                'verification_notes' => 'Spoke to caretaker, willing to host viewing.',
                'assigned_to'        => 999999, // not even a real user — must not be validated
            ])
        );

        $response->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertNull($listing->directions);
        $this->assertNull($listing->contact_type);
        $this->assertNull($listing->verification_notes);
        $this->assertNull($listing->assigned_to);
    }

    // =========================================================================
    // Invalid values across the fields admin can edit at the not_called stage.
    // =========================================================================

    public function test_it_validates_invalid_values_with_messages(): void
    {
        $this->asAdmin();
        $listingA = $this->makeUnverifiedListing(1);
        $listingB = $this->makeUnverifiedListing(1);

        $response = $this->put(route('admin.listings.unverified-update', $listingA->uuid), [
            'title'          => 'ok',
            'listing_type'   => 'spaceship',
            'barangay'       => 'atlantis',
            'latitude'       => 200,
            'longitude'      => 500,
            'amenities'      => [999999],
            'photos'         => [
                ['key' => 'listings/foreign-uuid/cover.jpg', 'name' => 'x', 'size' => 1],
                ['existing_id' => $listingB->images->first()->id],
            ],
            'contact_phone'  => 'not-a-phone',
            'source_site'    => 'made-up-site',
            'source_url'     => 'not-a-url',
            'prequal_status' => 'maybe-called',
        ]);

        $response->assertSessionHasErrors([
            'listing_type'  => 'Invalid listing type.',
            'barangay'      => 'Invalid barangay.',
            'latitude'      => 'Latitude must be between -90 and 90.',
            'longitude'     => 'Longitude must be between -180 and 180.',
            'amenities.0'   => "One or more selected amenities don't exist.",
            'photos.0.key'  => 'Invalid photo key.',
            'photos.1.existing_id',
            'contact_phone',
            'source_site'   => 'Invalid source site.',
            'source_url'    => 'Source URL must be a valid URL.',
            'prequal_status',
        ]);
    }

    // =========================================================================
    // Photo XOR — each entry must have exactly one of {existing_id, key}.
    // =========================================================================

    public function test_it_rejects_photo_entries_with_both_or_neither_id_and_key(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing(1);
        $existing = $listing->images->first();

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, [
                'photos' => [['existing_id' => $existing->id, 'key' => 'tmp/x', 'name' => 'x', 'size' => 1]],
            ])
        )->assertSessionHasErrors('photos.0');

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, [
                'photos' => [['name' => 'x', 'size' => 1]],
            ])
        )->assertSessionHasErrors('photos.0');
    }

    // =========================================================================
    // Photos array bound — too many.
    // =========================================================================

    public function test_it_rejects_too_many_photos(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing(2);

        $photos = $this->existingPhotos($listing);
        for ($i = 0; $i < 19; $i++) {
            $photos[] = $this->seedTmpPhoto("new-{$i}");
        }

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, ['photos' => $photos])
        )->assertSessionHasErrors(['photos' => 'Maximum 20 photos allowed.']);
    }

    // =========================================================================
    // contact_phone normalized to E.164 — accepts 09171234567, stores
    // +639171234567. Mirrors store()'s rule via PhMobile::normalize().
    // =========================================================================

    public function test_it_normalizes_contact_phone_to_e164(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, ['contact_phone' => '09171234567'])
        )->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', $listing->fresh()->contact_phone);
    }

    // =========================================================================
    // Happy path — admin updates basic lead details with prequal_status still
    // 'not_called'. Persists scalars + source fields + photos + amenities;
    // redirects to unverified-listings.
    // =========================================================================

    public function test_it_updates_basic_details_when_prequal_status_is_not_called(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing(2);

        $newAmenityA = Amenity::factory()->create();
        $newAmenityB = Amenity::factory()->create();

        $newPhoto = $this->seedTmpPhoto('brand-new');
        $photos = array_merge($this->existingPhotos($listing), [$newPhoto]);

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, [
                'title'         => 'Brand New Title',
                'description'   => 'Brand new desc.',
                'listing_type'  => 'condo',
                'price_monthly' => 22500,
                'barangay'      => 'carmen',
                'beds'          => 3,
                'baths'         => 2,
                'sqm'           => 90,
                'latitude'      => 8.4635,
                'longitude'     => 124.6573,
                'amenities'     => [$newAmenityA->id, $newAmenityB->id],
                'photos'        => $photos,
                'contact_phone' => '+639181234567',
                'source_site'   => 'rent_ph',
                'source_url'    => 'https://rent.ph/property/sample',
            ])
        );

        $response->assertRedirect(route('admin.unverified-listings.index'));
        $response->assertSessionHas('success');

        $listing->refresh();

        $this->assertSame('Brand New Title',                 $listing->title);
        $this->assertSame('Brand new desc.',                 $listing->description);
        $this->assertSame('condo',                           $listing->type);
        $this->assertSame(22500,                             $listing->price_monthly);
        $this->assertSame('carmen',                          $listing->barangay);
        $this->assertSame(3,                                 $listing->beds);
        $this->assertSame(2,                                 $listing->baths);
        $this->assertSame(90,                                $listing->sqm);
        $this->assertEqualsWithDelta(8.4635,                 $listing->latitude,  0.0000001);
        $this->assertEqualsWithDelta(124.6573,               $listing->longitude, 0.0000001);
        $this->assertSame('+639181234567',                   $listing->contact_phone);
        $this->assertSame('rent_ph',                         $listing->source_site);
        $this->assertSame('https://rent.ph/property/sample', $listing->source_url);
        $this->assertSame('not_called',                      $listing->prequal_status);

        // Photos: new added at end, existing preserved
        $images = $listing->fresh()->images()->orderBy('sort_order')->get();
        $this->assertCount(3, $images);
        Storage::disk('s3')->assertExists("listings/{$listing->uuid}/brand-new");
        Storage::disk('s3')->assertMissing('tmp/brand-new');

        // Amenities synced
        $attached = $listing->fresh()->amenities->pluck('id')->sort()->values()->all();
        $expected = collect([$newAmenityA->id, $newAmenityB->id])->sort()->values()->all();
        $this->assertSame($expected, $attached);
    }

    // =========================================================================
    // Lifecycle audit log — every successful update writes one 'updated'
    // event with notes JSON of the request body.
    // =========================================================================

    public function test_it_writes_an_updated_lifecycle_event(): void
    {
        $admin = $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing, ['title' => 'New Title After Edit'])
        );

        $events = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->where('event_type', 'updated')
            ->get();

        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertNull($event->reason);

        $this->assertNotNull($event->notes);
        $notes = json_decode($event->notes, true);
        $this->assertIsArray($notes);
        $this->assertSame('New Title After Edit', $notes['title']);
    }

    // =========================================================================
    // Required fields when called_yes: title, contact_phone, prequal_status,
    // directions, contact_type. Field-officer assignment (assigned_to) and
    // verification_notes stay nullable — admin can save mid-call before
    // deciding on a field officer.
    // =========================================================================

    public function test_it_requires_title_contact_phone_directions_and_contact_type_when_prequal_status_is_called_yes(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(route('admin.listings.unverified-update', $listing->uuid), [
            'title'          => '',
            'contact_phone'  => '',
            'prequal_status' => 'called_yes',
            'photos'         => $this->existingPhotos($listing),
            // directions, contact_type omitted — should error
            // assigned_to, verification_notes omitted — should NOT error
        ]);

        $response->assertSessionHasErrors([
            'title'         => 'Title is required.',
            'contact_phone' => 'Contact phone is required.',
            'directions'    => 'Directions are required.',
            'contact_type'  => 'Contact type is required.',
        ]);

        $response->assertSessionDoesntHaveErrors(['assigned_to', 'verification_notes']);
    }

    // =========================================================================
    // Invalid values for call-context fields on called_yes — directions over
    // 500 chars, contact_type outside enum, verification_notes over 2000,
    // assigned_to non-existent user.
    // =========================================================================

    public function test_it_validates_invalid_call_context_values_when_prequal_status_is_called_yes(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(route('admin.listings.unverified-update', $listing->uuid), [
            'title'              => 'ok',
            'contact_phone'      => '+639171234567',
            'prequal_status'     => 'called_yes',
            'photos'             => $this->existingPhotos($listing),
            'directions'         => str_repeat('x', 501),
            'contact_type'       => 'spy',
            'verification_notes' => str_repeat('y', 2001),
            'assigned_to'        => 999999,
        ]);

        $response->assertSessionHasErrors([
            'directions',
            'contact_type',
            'verification_notes',
            'assigned_to',
        ]);
    }

    // =========================================================================
    // Happy path — called_yes persists every call-context field. Complement
    // to the not_called silent-ignore test: call-context fields ARE
    // persisted on this slice.
    // =========================================================================

    public function test_it_persists_call_context_fields_when_prequal_status_is_called_yes(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();
        $field = $this->makeFieldUser('Maria Cruz');

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, [
                'directions'         => 'Past the gate, second left.',
                'contact_type'       => 'authorized_rep',
                'verification_notes' => 'Spoke to caretaker, willing to host viewing.',
                'assigned_to'        => $field->id,
            ])
        );

        $response->assertRedirect(route('admin.unverified-listings.index'));
        $response->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('called_yes',                                   $listing->prequal_status);
        $this->assertSame('Past the gate, second left.',                  $listing->directions);
        $this->assertSame('authorized_rep',                               $listing->contact_type);
        $this->assertSame('Spoke to caretaker, willing to host viewing.', $listing->verification_notes);
        $this->assertSame($field->id,                                     $listing->assigned_to);
    }

    // =========================================================================
    // assigned_to must reference a user with the 'field' role. Non-field
    // users (super-admin, plain users) → 422 even on the called_yes slice.
    // Defense-in-depth — the dropdown only offers field users, but a crafted
    // payload could submit anyone.
    // =========================================================================

    public function test_it_rejects_assigned_to_when_user_lacks_field_role(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();
        $randomUser = User::factory()->create(); // no role

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => $randomUser->id])
        );

        $response->assertSessionHasErrors('assigned_to');
    }

    // =========================================================================
    // queue_status auto-transitions to 'assigned' when admin assigns a field
    // officer. unassigned → assigned, no separate UI action.
    // =========================================================================

    public function test_it_transitions_queue_status_to_assigned_when_assigning_field_officer(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing(2, ['queue_status' => 'unassigned']);
        $field = $this->makeFieldUser();

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => $field->id])
        );

        $listing->refresh();
        $this->assertSame('assigned',  $listing->queue_status);
        $this->assertSame($field->id,  $listing->assigned_to);
    }

    // =========================================================================
    // queue_status auto-transitions back to 'unassigned' when admin clears
    // the assignment. Symmetric reverse — admin changes their mind, the
    // queue state follows.
    // =========================================================================

    public function test_it_transitions_queue_status_to_unassigned_when_clearing_assignment(): void
    {
        $this->asAdmin();
        $field = $this->makeFieldUser();
        $listing = $this->makeUnverifiedListing(2, [
            'queue_status' => 'assigned',
            'assigned_to'  => $field->id,
        ]);

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => null])
        );

        $listing->refresh();
        $this->assertSame('unassigned', $listing->queue_status);
        $this->assertNull($listing->assigned_to);
    }

    // =========================================================================
    // Rollback guard — once a listing has been called (called_yes OR
    // no_answer), it can never go back to not_called. Pretending a call
    // didn't happen would corrupt the audit trail and orphan the assignee.
    // =========================================================================

    public function test_it_rejects_transition_to_not_called_when_listing_was_called_yes(): void
    {
        $this->asAdmin();
        $field = $this->makeFieldUser();
        $listing = $this->makeUnverifiedListing(2, [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $field->id,
            'directions'     => 'Past the gate.',
            'contact_type'   => 'owner',
        ]);

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing)
        );

        $response->assertSessionHasErrors('prequal_status');
    }

    public function test_it_rejects_transition_to_not_called_when_listing_was_no_answer(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing(2, ['prequal_status' => 'no_answer']);

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->notCalledPayload($listing)
        );

        $response->assertSessionHasErrors('prequal_status');
    }

    // =========================================================================
    // Lock-in: once a listing was called_yes, only called_yes is allowed
    // going forward — including the lateral move to no_answer. A successful
    // call can't be rewritten as "no answer" after the fact.
    // =========================================================================

    public function test_it_rejects_transition_to_no_answer_when_listing_was_called_yes(): void
    {
        $this->asAdmin();
        $field = $this->makeFieldUser();
        $listing = $this->makeUnverifiedListing(2, [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $field->id,
            'directions'     => 'Past the gate.',
            'contact_type'   => 'owner',
        ]);

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            array_merge($this->notCalledPayload($listing), ['prequal_status' => 'no_answer'])
        );

        $response->assertSessionHasErrors('prequal_status');
    }

    // =========================================================================
    // no_answer slice — behaves like not_called (only title + contact_phone
    // required; call-context fields silently ignored). Lock-in already
    // covered by `test_it_rejects_transition_to_not_called_when_listing_was_
    // no_answer` and the called_yes lateral test above.
    // =========================================================================

    public function test_it_requires_only_title_and_contact_phone_when_prequal_status_is_no_answer(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(route('admin.listings.unverified-update', $listing->uuid), [
            'title'          => '',
            'contact_phone'  => '',
            'prequal_status' => 'no_answer',
            'photos'         => [],
        ]);

        $response->assertSessionHasErrors([
            'title'         => 'Title is required.',
            'contact_phone' => 'Contact phone is required.',
        ]);

        $response->assertSessionDoesntHaveErrors([
            'listing_type', 'barangay', 'beds', 'baths', 'sqm', 'price_monthly',
            'source_site', 'source_url', 'photos',
            'directions', 'contact_type', 'verification_notes', 'assigned_to',
        ]);
    }

    public function test_it_silently_ignores_call_context_fields_when_prequal_status_is_no_answer(): void
    {
        $this->asAdmin();
        $listing = $this->makeUnverifiedListing();

        $response = $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            [
                'title'              => 'Updated Lead Title',
                'contact_phone'      => '+639171234567',
                'prequal_status'     => 'no_answer',
                'photos'             => $this->existingPhotos($listing),
                'directions'         => 'Past the gate.',
                'contact_type'       => 'authorized_rep',
                'verification_notes' => 'Spoke to caretaker.',
                'assigned_to'        => 999999,
            ]
        );

        $response->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('no_answer', $listing->prequal_status);
        $this->assertNull($listing->directions);
        $this->assertNull($listing->contact_type);
        $this->assertNull($listing->verification_notes);
        $this->assertNull($listing->assigned_to);
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');
        $listing = $this->makeUnverifiedListing();

        $this->put(route('admin.listings.unverified-update', $listing->uuid), $this->notCalledPayload($listing))
            ->assertForbidden();
    }

    // =========================================================================
    // assigned_at lifecycle (4) — set on flip null→user, reset on reassign,
    // cleared on flip user→null, untouched when assigned_to unchanged.
    // =========================================================================

    public function test_it_sets_assigned_at_when_assigned_to_flips_from_null_to_user(): void
    {
        $this->asAdmin();
        $field = $this->makeFieldUser();
        $listing = $this->makeUnverifiedListing(state: [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'unassigned',
            'assigned_to'    => null,
            'assigned_at'    => null,
        ]);

        $before = Carbon::now()->subSecond();
        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => $field->id])
        );
        $after = Carbon::now()->addSecond();

        $listing->refresh();
        $this->assertSame($field->id, $listing->assigned_to);
        $this->assertNotNull($listing->assigned_at);
        $this->assertTrue(
            $listing->assigned_at->between($before, $after),
            'assigned_at must be ~Carbon::now() at request time'
        );
    }

    public function test_it_resets_assigned_at_when_reassigned_to_different_user(): void
    {
        $this->asAdmin();
        $officerA = $this->makeFieldUser('Officer A');
        $officerB = $this->makeFieldUser('Officer B');

        $oldAssignedAt = Carbon::now()->subDays(3);
        $listing = $this->makeUnverifiedListing(state: [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $officerA->id,
            'assigned_at'    => $oldAssignedAt,
        ]);

        $before = Carbon::now()->subSecond();
        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => $officerB->id])
        );
        $after = Carbon::now()->addSecond();

        $listing->refresh();
        $this->assertSame($officerB->id, $listing->assigned_to);
        $this->assertTrue(
            $listing->assigned_at->between($before, $after),
            'assigned_at must reset to ~Carbon::now() on reassignment'
        );
        $this->assertTrue(
            $listing->assigned_at->greaterThan($oldAssignedAt),
            'assigned_at must be newer than the previous timestamp'
        );
    }

    public function test_it_clears_assigned_at_when_assigned_to_flips_to_null(): void
    {
        $this->asAdmin();
        $officer = $this->makeFieldUser();
        $listing = $this->makeUnverifiedListing(state: [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $officer->id,
            'assigned_at'    => Carbon::now()->subDays(1),
        ]);

        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, ['assigned_to' => null])
        );

        $listing->refresh();
        $this->assertNull($listing->assigned_to);
        $this->assertNull($listing->assigned_at);
    }

    public function test_it_leaves_assigned_at_untouched_when_assigned_to_unchanged(): void
    {
        $this->asAdmin();
        $officer = $this->makeFieldUser();

        $original = Carbon::now()->subDays(2)->startOfMinute();
        $listing = $this->makeUnverifiedListing(state: [
            'prequal_status' => 'called_yes',
            'queue_status'   => 'assigned',
            'assigned_to'    => $officer->id,
            'assigned_at'    => $original,
        ]);

        // Edit only the title; keep assigned_to the same.
        $this->put(
            route('admin.listings.unverified-update', $listing->uuid),
            $this->calledYesPayload($listing, [
                'title'       => 'Edited title only',
                'assigned_to' => $officer->id,
            ])
        );

        $listing->refresh();
        $this->assertSame($officer->id, $listing->assigned_to);
        $this->assertSame(
            $original->toDateTimeString(),
            $listing->assigned_at->toDateTimeString(),
            'assigned_at must not change when assigned_to is unchanged'
        );
    }
}
