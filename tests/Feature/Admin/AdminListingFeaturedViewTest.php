<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingFeaturedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.featured-listings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_only_featured_verified_listings_with_cover_photo_and_in_featured_order(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 4 featured-verified listings with images (cover photo). featured_order is
        // scrambled in creation order so the response's sort assertion is unambiguous.
        $orders = [4, 1, 3, 2];
        $featuredIds = [];
        foreach ($orders as $order) {
            $listing = Listing::factory()
                ->featured()
                ->withImages(2)
                ->create([
                    'type'        => 'apartment',
                    'barangay'    => 'pueblo_de_oro',
                    'is_verified' => true,
                    'verified_at' => Carbon::now()->subDays(10),
                ]);
            $listing->update(['featured_order' => $order]);
            $featuredIds[] = $listing->id;
        }

        // Noise that must NOT appear in the result —
        // exercises the four-slice filter exhaustively + the orphan case:

        // 2 verified-live but NOT featured
        for ($i = 0; $i < 2; $i++) {
            Listing::factory()->withImages(1)->create([
                'is_featured' => false,
                'is_verified' => true,
                'verified_at' => Carbon::now(),
                'type'        => 'condo',
                'barangay'    => 'carmen',
            ]);
        }

        // 2 unverified-live (is_verified=false, deleted_at=null)
        for ($i = 0; $i < 2; $i++) {
            Listing::factory()->create([
                'is_featured' => false,
                'is_verified' => false,
                'verified_at' => null,
                'type'        => 'studio',
                'barangay'    => 'lapasan',
            ]);
        }

        // 1 deactivated (is_verified=true, soft-deleted) — also featured to make sure
        // the deactivation hides it from the featured slice
        $deactivated = Listing::factory()->featured()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now()->subDays(30),
        ]);
        $deactivated->delete();

        // 1 orphan (is_featured=true, is_verified=false) — featuring without verification.
        // CRITICAL: this MUST be excluded. The page is the trusted-and-featured intersection.
        Listing::factory()->featured()->unverified()->create([
            'type'        => 'apartment',
            'barangay'    => 'kauswagan',
        ]);

        // ---- Hit the route ----
        $response = $this->get(route('admin.featured-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('featured');

        // Data — exactly 4 rows (the 4 featured-verified)
        $this->assertCount(4, $payload['data']);

        // Resource shape
        $first = $payload['data'][0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'type_label', 'barangay_label', 'price', 'cover_image_url', 'featured_order'],
            array_keys($first)
        );

        // Human-readable enum labels
        $this->assertSame('Apartment',     $first['type_label']);
        $this->assertSame('Pueblo de Oro', $first['barangay_label']);

        // Cover photo URL non-null on every row
        foreach ($payload['data'] as $row) {
            $this->assertNotNull($row['cover_image_url'],
                'cover_image_url must be non-null when listing has a display_image_id');
        }

        // Sort order — featured_order ASC: 1, 2, 3, 4
        $orders = array_map(fn ($r) => $r['featured_order'], $payload['data']);
        $this->assertSame([1, 2, 3, 4], $orders, 'Rows must be ordered by featured_order ASC');

        // CRITICAL: scope filter — every returned row's id must be from the
        // featured-verified set. None of the noise should leak.
        $returnedIds = array_map(fn ($r) => $r['id'], $payload['data']);
        foreach ($returnedIds as $id) {
            $this->assertContains($id, $featuredIds,
                "Row id {$id} should be from the featured-verified slice; non-featured, unverified, deactivated, or orphan leaked");
        }
    }

    public function test_it_dedupes_duplicate_featured_order_values_via_self_heal(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Three featured-verified listings, TWO with the same featured_order=2.
        // Edge case from manual DB tampering. Self-heal should dedupe by keeping
        // the lower-id duplicate at its existing value and bumping the later-id
        // collision to max+1. Same philosophy as null-handling: irregular rows
        // get appended to end via id-ASC tie-break.
        $lone = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        $lone->update(['featured_order' => 1]);

        $duplicateA = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        $duplicateA->update(['featured_order' => 2]);

        $duplicateB = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        $duplicateB->update(['featured_order' => 2]);

        $response = $this->get(route('admin.featured-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('featured');

        // All 3 rows present.
        $this->assertCount(3, $payload['data']);

        // After self-heal:
        //   $lone        — keeps featured_order=1 (unique, no collision).
        //   $duplicateA  — keeps featured_order=2 (lower id wins the tie-break).
        //   $duplicateB  — bumped to featured_order=3 (later id, collision detected → renumbered to max+1).
        $this->assertSame($lone->id, $payload['data'][0]['id']);
        $this->assertSame(1, $payload['data'][0]['featured_order']);

        $this->assertSame($duplicateA->id, $payload['data'][1]['id']);
        $this->assertSame(2, $payload['data'][1]['featured_order']);

        $this->assertSame($duplicateB->id, $payload['data'][2]['id']);
        $this->assertSame(3, $payload['data'][2]['featured_order']);

        // Verify DB state — duplicates are persistently dedupe'd, not just sort-order'd.
        $this->assertSame(1, $lone->fresh()->featured_order);
        $this->assertSame(2, $duplicateA->fresh()->featured_order);
        $this->assertSame(3, $duplicateB->fresh()->featured_order);
    }

    public function test_it_dedupes_duplicates_without_colliding_with_existing_higher_orders(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // lone=1, dupeA=2, dupeB=2, existing=3.
        // Self-heal must bump dupeB to 4 (max+1), NOT to 3 — otherwise it would
        // collide with the existing row at 3. Pins the "max(featured_order)+1"
        // base over any naive "next integer" approach.
        $lone = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        $lone->update(['featured_order' => 1]);

        $duplicateA = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        $duplicateA->update(['featured_order' => 2]);

        $duplicateB = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        $duplicateB->update(['featured_order' => 2]);

        $existingThree = Listing::factory()->featured()->withImages(1)->create([
            'is_verified' => true, 'verified_at' => Carbon::now(),
        ]);
        $existingThree->update(['featured_order' => 3]);

        $response = $this->get(route('admin.featured-listings.index'));
        $response->assertOk();

        $payload = $response->viewData('featured');
        $this->assertCount(4, $payload['data']);

        // Final layout: 1, 2, 3, 4 — duplicateB bumped past existingThree to 4.
        $this->assertSame($lone->id,           $payload['data'][0]['id']);
        $this->assertSame(1,                   $payload['data'][0]['featured_order']);

        $this->assertSame($duplicateA->id,     $payload['data'][1]['id']);
        $this->assertSame(2,                   $payload['data'][1]['featured_order']);

        $this->assertSame($existingThree->id,  $payload['data'][2]['id']);
        $this->assertSame(3,                   $payload['data'][2]['featured_order']);

        $this->assertSame($duplicateB->id,     $payload['data'][3]['id']);
        $this->assertSame(4,                   $payload['data'][3]['featured_order']);
    }

    public function test_it_auto_assigns_featured_order_to_unordered_featured_listings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // 3 featured-verified listings with featured_order=null (post-migration state).
        for ($i = 0; $i < 3; $i++) {
            Listing::factory()->featured()->create([
                'is_verified'    => true,
                'verified_at'    => Carbon::now(),
                'featured_order' => null,
            ]);
        }

        // Sanity: all three are null
        $this->assertSame(3, Listing::featured()->verified()->whereNull('featured_order')->count());

        // ---- Hit the route ----
        $response = $this->get(route('admin.featured-listings.index'));
        $response->assertOk();

        // After hitting the page, all three have non-null featured_order
        $this->assertSame(0, Listing::featured()->verified()->whereNull('featured_order')->count(),
            'Self-heal block must assign featured_order to every null row');

        // Sequential 1, 2, 3 in id order (matches the controller's whereNull(...)->get() iteration)
        $orders = Listing::featured()->verified()->orderBy('id')->pluck('featured_order')->all();
        $this->assertSame([1, 2, 3], $orders);
    }
}
