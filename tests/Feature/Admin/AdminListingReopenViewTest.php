<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingReopenViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function rejectedListing(): Listing
    {
        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
        $listing->delete();
        return $listing->fresh();
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = $this->rejectedListing();

        $response = $this->get(route('admin.listings.reopen.show', ['listing' => $listing->uuid]));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_404s_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.listings.reopen.show', ['listing' => '00000000-0000-0000-0000-000000000000']));

        $response->assertNotFound();
    }

    public function test_it_404s_for_live_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
        // NOT soft-deleted — unverified-live, not rejected.

        $response = $this->get(route('admin.listings.reopen.show', ['listing' => $listing->uuid]));

        $response->assertNotFound();
    }

    public function test_it_404s_for_deactivated_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(7),
        ]);
        $listing->delete();
        // is_verified=true AND deleted_at NOT NULL = deactivated slice (NOT rejected).

        $response = $this->get(route('admin.listings.reopen.show', ['listing' => $listing->uuid]));

        $response->assertNotFound();
    }

    public function test_it_returns_review_page_with_eager_loaded_relations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = $this->rejectedListing();

        // Seed the prior `rejected` lifecycle event — represents the real-world
        // state where the review page is reached only after a previous reject.
        $event = ListingLifecycleEvent::factory()->create([
            'listing_id' => $listing->id,
            'actor_id'   => $admin->id,
            'event_type' => 'rejected',
            'reason'     => null,
            'notes'      => null,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $response = $this->get(route('admin.listings.reopen.show', ['listing' => $listing->uuid]));

        $response->assertOk();

        $viewListing = $response->viewData('listing');
        $this->assertNotNull($viewListing);
        $this->assertSame($listing->id,   $viewListing->id);
        $this->assertSame($listing->uuid, $viewListing->uuid);

        // Three relations must be eager-loaded — without them, the review page
        // would N+1 (or worse, render with the wrong "current" event) at the
        // Blade layer.
        $this->assertTrue($viewListing->relationLoaded('images'),
            'images relation must be eager-loaded for the photo gallery');
        $this->assertTrue($viewListing->relationLoaded('amenities'),
            'amenities relation must be eager-loaded for the amenities card');
        $this->assertTrue($viewListing->relationLoaded('latestLifecycleEvent'),
            'latestLifecycleEvent must be eager-loaded for the rejection banner');

        $this->assertNotNull($viewListing->latestLifecycleEvent);
        $this->assertSame($event->id,  $viewListing->latestLifecycleEvent->id);
        $this->assertSame('rejected',  $viewListing->latestLifecycleEvent->event_type);
        $this->assertNull($viewListing->latestLifecycleEvent->reason);
        $this->assertNull($viewListing->latestLifecycleEvent->notes);

        $this->assertTrue($viewListing->latestLifecycleEvent->relationLoaded('actor'),
            'actor must be eager-loaded on the latest event for the rejection banner "by whom"');
        $this->assertSame($admin->id, $viewListing->latestLifecycleEvent->actor->id);
    }
}
