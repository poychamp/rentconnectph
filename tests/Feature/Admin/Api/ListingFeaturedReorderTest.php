<?php

namespace Tests\Feature\Admin\Api;

use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class ListingFeaturedReorderTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function featuredVerified(int $order): Listing
    {
        $listing = Listing::factory()->featured()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(5),
        ]);
        $listing->update(['featured_order' => $order]);
        return $listing->fresh();
    }

    public function test_it_returns_401_for_guest(): void
    {
        $response = $this->putJson(route('admin.api.listings.featured-sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_it_validates_order_payload(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Missing `order`
        $this->putJson(route('admin.api.listings.featured-sort'), [])
            ->assertJsonValidationErrors(['order']);

        // `order` not an array
        $this->putJson(route('admin.api.listings.featured-sort'), ['order' => 'not-an-array'])
            ->assertJsonValidationErrors(['order']);

        // `order` empty array
        $this->putJson(route('admin.api.listings.featured-sort'), ['order' => []])
            ->assertJsonValidationErrors(['order']);

        // `order[*]` not a string / not a uuid
        $this->putJson(route('admin.api.listings.featured-sort'), ['order' => [123]])
            ->assertJsonValidationErrors(['order.0']);

        $this->putJson(route('admin.api.listings.featured-sort'), ['order' => ['not-a-uuid']])
            ->assertJsonValidationErrors(['order.0']);
    }

    public function test_it_rejects_payload_containing_non_featured_uuids(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $featuredA = $this->featuredVerified(1);
        $featuredB = $this->featuredVerified(2);

        // Verified-live but NOT featured (cross-slice — must be rejected).
        $notFeatured = Listing::factory()->create([
            'is_featured' => false,
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $response = $this->putJson(route('admin.api.listings.featured-sort'), [
            'order' => [$featuredA->uuid, $notFeatured->uuid],
        ]);

        $response->assertJsonValidationErrors(['order']);

        // DB state untouched — no partial writes.
        $this->assertSame(1, $featuredA->fresh()->featured_order);
        $this->assertSame(2, $featuredB->fresh()->featured_order);
        $this->assertNull($notFeatured->fresh()->featured_order);
    }

    public function test_it_rejects_payload_containing_uuids_for_featured_but_unverified_listings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $featuredA = $this->featuredVerified(1);
        $featuredB = $this->featuredVerified(2);

        // Orphan: is_featured=true AND is_verified=false. NOT in the curated slice.
        $orphan = Listing::factory()->featured()->unverified()->create();

        $response = $this->putJson(route('admin.api.listings.featured-sort'), [
            'order' => [$featuredA->uuid, $orphan->uuid],
        ]);

        $response->assertJsonValidationErrors(['order']);

        // DB state untouched.
        $this->assertSame(1, $featuredA->fresh()->featured_order);
        $this->assertSame(2, $featuredB->fresh()->featured_order);
    }

    public function test_it_persists_new_order_and_writes_sequential_integers(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $first  = $this->featuredVerified(1);
        $second = $this->featuredVerified(2);
        $third  = $this->featuredVerified(3);

        // Submit a swap: third → 1, first → 2, second → 3.
        $response = $this->putJson(route('admin.api.listings.featured-sort'), [
            'order' => [$third->uuid, $first->uuid, $second->uuid],
        ]);

        $response->assertOk();
        $response->assertExactJson(['ok' => true]);

        // Persisted ordinals
        $this->assertSame(1, $third->fresh()->featured_order);
        $this->assertSame(2, $first->fresh()->featured_order);
        $this->assertSame(3, $second->fresh()->featured_order);

        // No gaps, no duplicates, no leftovers — the full slice's set of
        // featured_order values must be exactly {1, 2, 3}.
        $sliceOrders = Listing::featured()->verified()
            ->orderBy('featured_order')
            ->pluck('featured_order')
            ->all();
        $this->assertSame([1, 2, 3], $sliceOrders);
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->putJson(route('admin.api.listings.featured-sort'), ['order' => []])
            ->assertForbidden();
    }
}
