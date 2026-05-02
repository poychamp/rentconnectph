<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingDeactivateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'reason' => 'rented_out_closed',
            'notes'  => 'Tenant moved in 2026-04-25.',
        ], $overrides);
    }

    private function verifiedLiveListing(): Listing
    {
        return Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(7),
        ]);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload()
        );

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_404s_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => '00000000-0000-0000-0000-000000000000']),
            $this->validPayload()
        );

        $response->assertNotFound();
    }

    public function test_it_422s_when_reason_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            ['notes' => 'no reason here']
        );

        $response->assertSessionHasErrors(['reason' => 'Reason is required.'], null, 'deactivate');
        $this->assertNotSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_422s_when_reason_is_invalid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload(['reason' => 'not-a-real-reason'])
        );

        $response->assertSessionHasErrors(['reason' => 'Invalid reason.'], null, 'deactivate');
        $this->assertNotSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_422s_when_notes_exceed_max(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload(['notes' => str_repeat('x', 1001)])
        );

        $response->assertSessionHasErrors(['notes' => 'Notes are too long (max 1000 characters).'], null, 'deactivate');
        $this->assertNotSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_rejects_when_listing_already_trashed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = $this->verifiedLiveListing();
        $listing->delete();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload()
        );

        // Route-model binding hides trashed rows via the global SoftDeletes scope → 404.
        // Pinning this behavior so a future binding change (e.g. ->withTrashed()) doesn't
        // silently flip the response shape.
        $response->assertNotFound();
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_rejects_when_listing_is_unverified_live(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload()
        );

        $response->assertSessionHasErrors('listing', null, 'deactivate');
        $this->assertNotSoftDeleted('listings', ['id' => $listing->id]);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_redirects_to_featured_listings_when_from_is_featured(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload(['from' => 'featured'])
        );

        $response->assertRedirect(route('admin.featured-listings.index'));
    }

    public function test_it_soft_deletes_listing_and_writes_lifecycle_event_and_redirects(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $before = Carbon::now()->subSecond();
        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            $this->validPayload([
                'reason' => 'rented_out_closed',
                'notes'  => 'Tenant moved in 2026-04-25.',
            ])
        );
        $after = Carbon::now()->addSecond();

        $response->assertRedirect(route('admin.verified-listings.index'));
        $response->assertSessionHas('success', "Listing '{$listing->title}' deactivated.");

        $reloaded = Listing::withTrashed()->find($listing->id);
        $this->assertNotNull($reloaded->deleted_at);
        $this->assertTrue((bool) $reloaded->is_verified);

        $events = ListingLifecycleEvent::where('listing_id', $listing->id)->get();
        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('deactivated',                  $event->event_type);
        $this->assertSame('rented_out_closed',            $event->reason);
        $this->assertSame($admin->id,                     $event->actor_id);
        $this->assertSame('Tenant moved in 2026-04-25.',  $event->notes);
        $this->assertTrue(
            $event->created_at->between($before, $after),
            'created_at must be within 1s of Carbon::now() at request time'
        );
    }

    public function test_it_persists_null_notes_when_omitted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        $listing = $this->verifiedLiveListing();

        $response = $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            ['reason' => 'unavailable']
        );

        $response->assertRedirect(route('admin.verified-listings.index'));

        $event = ListingLifecycleEvent::where('listing_id', $listing->id)->firstOrFail();
        $this->assertNull($event->notes);
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');
        $listing = $this->verifiedLiveListing();

        $this->put(
            route('admin.listings.deactivate', ['listing' => $listing->uuid]),
            ['reason' => 'unavailable']
        )->assertForbidden();
    }
}
