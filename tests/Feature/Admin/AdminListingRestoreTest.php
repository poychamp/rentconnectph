<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingRestoreTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function deactivatedListing(): Listing
    {
        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(30),
        ]);
        $listing->delete();
        return $listing->fresh();
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->deactivatedListing();

        $response = $this->put(route('admin.listings.restore', ['listing' => $listing->uuid]));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_404s_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->put(route('admin.listings.restore', ['listing' => '00000000-0000-0000-0000-000000000000']));

        $response->assertNotFound();
    }

    public function test_it_rejects_when_listing_is_live(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(7),
        ]);
        // NOT soft-deleted — verified-live, not deactivated.

        $response = $this->put(route('admin.listings.restore', ['listing' => $listing->uuid]));

        $response->assertSessionHasErrors('listing', null, 'restore');
        $this->assertNull(Listing::find($listing->id)->deleted_at);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_rejects_when_listing_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
        $listing->delete();
        // is_verified=false AND deleted_at NOT NULL = rejected slice (NOT deactivated).

        $response = $this->put(route('admin.listings.restore', ['listing' => $listing->uuid]));

        $response->assertSessionHasErrors('listing', null, 'restore');
        // Rejected listing stays trashed:
        $this->assertNotNull(Listing::withTrashed()->find($listing->id)->deleted_at);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_restores_listing_and_writes_lifecycle_event_and_redirects(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = $this->deactivatedListing();

        // Seed the prior `deactivated` event — represents the real-world state where
        // Restore is reached only after a previous deactivation. Pins that the restore
        // writes a NEW `reactivated` row alongside, NOT an overwrite of the existing one.
        ListingLifecycleEvent::factory()->deactivated()->create([
            'listing_id' => $listing->id,
            'actor_id'   => $admin->id,
            'reason'     => 'rented_out',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $before = Carbon::now()->subSecond();
        $response = $this->put(route('admin.listings.restore', ['listing' => $listing->uuid]));
        $after = Carbon::now()->addSecond();

        $response->assertRedirect(route('admin.deactivated-listings.index'));
        $response->assertSessionHas('success', "Listing '{$listing->title}' restored.");

        // Listing is no longer trashed (default Listing::find() finds it).
        $reloaded = Listing::find($listing->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->deleted_at);
        $this->assertTrue((bool) $reloaded->is_verified);

        // TWO events on the listing: prior `deactivated` + new `reactivated`.
        $events = ListingLifecycleEvent::where('listing_id', $listing->id)
            ->orderBy('created_at')
            ->get();
        $this->assertCount(2, $events);

        $this->assertSame('deactivated', $events[0]->event_type);

        $reactivated = $events[1];
        $this->assertSame('reactivated', $reactivated->event_type);
        $this->assertNull($reactivated->reason);
        $this->assertNull($reactivated->notes);
        $this->assertSame($admin->id, $reactivated->actor_id);
        $this->assertTrue(
            $reactivated->created_at->between($before, $after),
            'reactivated event created_at must be within 1s of Carbon::now() at request time'
        );
    }
}
