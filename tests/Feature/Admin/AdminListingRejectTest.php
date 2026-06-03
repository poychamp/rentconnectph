<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingRejectTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function unverifiedLiveListing(): Listing
    {
        return Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->unverifiedLiveListing();

        $response = $this->put(route('admin.listings.reject', ['listing' => $listing->uuid]));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_404s_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->put(route('admin.listings.reject', ['listing' => '00000000-0000-0000-0000-000000000000']));

        $response->assertNotFound();
    }

    public function test_it_rejects_when_listing_is_verified_live(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(7),
        ]);
        // NOT soft-deleted — verified-live, not unverified.

        $response = $this->put(route('admin.listings.reject', ['listing' => $listing->uuid]));

        $response->assertSessionHasErrors('listing', null, 'reject');
        $this->assertNull(Listing::find($listing->id)->deleted_at);
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
    }

    public function test_it_404s_when_listing_is_already_trashed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Pick the deactivated slice. The rejected slice (is_verified=false +
        // trashed) is functionally identical for this test's purpose — both are
        // trashed, both 404 at the binding layer because the route has no
        // withTrashed().
        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(30),
        ]);
        $listing->delete();

        $response = $this->put(route('admin.listings.reject', ['listing' => $listing->uuid]));

        $response->assertNotFound();
        $this->assertDatabaseCount('listing_lifecycle_events', 0);
        $this->assertNotNull(Listing::withTrashed()->find($listing->id)->deleted_at);
    }

    public function test_it_rejects_listing_and_writes_lifecycle_event_and_redirects(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = $this->unverifiedLiveListing();

        $before = Carbon::now()->subSecond();
        $response = $this->put(route('admin.listings.reject', ['listing' => $listing->uuid]));
        $after = Carbon::now()->addSecond();

        $response->assertRedirect(route('admin.unverified-listings.index'));
        $response->assertSessionHas('success', "Listing '{$listing->title}' rejected.");

        // Listing is now soft-deleted.
        $reloaded = Listing::withTrashed()->find($listing->id);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->deleted_at);
        $this->assertFalse((bool) $reloaded->is_verified);  // is_verified preserved (still false)

        // Default-scope query no longer finds it.
        $this->assertNull(Listing::find($listing->id));

        // Exactly ONE lifecycle event for this listing — the new `rejected` row.
        $events = ListingLifecycleEvent::where('listing_id', $listing->id)->get();
        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('rejected', $event->event_type);
        $this->assertNull($event->reason);
        $this->assertNull($event->notes);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertTrue(
            $event->created_at->between($before, $after),
            'rejected event created_at must be within 1s of Carbon::now() at request time'
        );
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');
        $listing = $this->unverifiedLiveListing();

        $this->put(route('admin.listings.reject', $listing->uuid))
            ->assertForbidden();
    }
}
