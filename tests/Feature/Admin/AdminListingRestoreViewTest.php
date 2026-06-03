<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\ListingLifecycleEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingRestoreViewTest extends TestCase
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

        $response = $this->get(route('admin.listings.restore.show', ['listing' => $listing->uuid]));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_404s_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.listings.restore.show', ['listing' => '00000000-0000-0000-0000-000000000000']));

        $response->assertNotFound();
    }

    public function test_it_404s_for_live_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(7),
        ]);
        // NOT soft-deleted — verified-live, not deactivated.

        $response = $this->get(route('admin.listings.restore.show', ['listing' => $listing->uuid]));

        $response->assertNotFound();
    }

    public function test_it_404s_for_rejected_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
        $listing->delete();
        // is_verified=false AND deleted_at NOT NULL = rejected slice (NOT deactivated).

        $response = $this->get(route('admin.listings.restore.show', ['listing' => $listing->uuid]));

        $response->assertNotFound();
    }

    public function test_it_returns_review_page_with_eager_loaded_relations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = $this->deactivatedListing();

        // Seed the prior `deactivated` lifecycle event — represents the real-world
        // state where the review page is reached only after a previous deactivation.
        $event = ListingLifecycleEvent::factory()->deactivated()->create([
            'listing_id' => $listing->id,
            'actor_id'   => $admin->id,
            'reason'     => 'rented_out',
            'notes'      => 'Tenant moved in.',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $response = $this->get(route('admin.listings.restore.show', ['listing' => $listing->uuid]));

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
            'latestLifecycleEvent must be eager-loaded for the deactivation banner');
        $this->assertTrue($viewListing->relationLoaded('listingContact'),
            'listingContact must be eager-loaded for the contact card');

        $this->assertNotNull($viewListing->latestLifecycleEvent);
        $this->assertSame($event->id,    $viewListing->latestLifecycleEvent->id);
        $this->assertSame('deactivated', $viewListing->latestLifecycleEvent->event_type);
        $this->assertSame('rented_out',  $viewListing->latestLifecycleEvent->reason);

        $this->assertTrue($viewListing->latestLifecycleEvent->relationLoaded('actor'),
            'actor must be eager-loaded on the latest event for the deactivation banner "by whom"');
        $this->assertSame($admin->id, $viewListing->latestLifecycleEvent->actor->id);
    }
}
