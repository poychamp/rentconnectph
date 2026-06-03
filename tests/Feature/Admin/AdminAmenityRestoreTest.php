<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityRestoreTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    private function trashedAmenity(array $overrides = []): Amenity
    {
        $amenity = Amenity::factory()->create(array_merge([
            'name'       => 'Air Conditioning',
            'slug'       => 'aircon',
            'icon'       => 'aircon',
            'sort_order' => null,
        ], $overrides));
        $amenity->delete();
        return $amenity;
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $amenity = $this->trashedAmenity();

        $this->put(route('admin.amenities.restore', $amenity->uuid))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $amenity = $this->trashedAmenity();

        $this->put(route('admin.amenities.restore', $amenity->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $this->asAdmin();

        $this->put(route('admin.amenities.restore', '019dc5d9-ff77-7381-9755-000000000000'))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_an_active_amenity(): void
    {
        // Restore is trashed-only. An active row hitting restore must 404 —
        // there's nothing to restore. Defense-in-depth: the route binding uses
        // ->withTrashed() so it can find soft-deleted rows, but the controller
        // re-checks trashed() to bounce active UUIDs.
        $this->asAdmin();

        $active = Amenity::factory()->create();

        $this->put(route('admin.amenities.restore', $active->uuid))
            ->assertNotFound();
    }

    public function test_it_restores_the_amenity_and_redirects_to_deleted_index_on_success(): void
    {
        $this->asAdmin();

        $amenity = $this->trashedAmenity();
        $this->assertNotNull($amenity->fresh()->deleted_at);

        $response = $this->put(route('admin.amenities.restore', $amenity->uuid));

        $response->assertRedirect(route('admin.deleted-amenities.index'));
        $response->assertSessionHas('success');

        $restored = Amenity::find($amenity->id);
        $this->assertNotNull($restored, 'Restored row must surface via the default scope (no longer trashed)');
        $this->assertNull($restored->deleted_at, 'deleted_at must clear on restore');

        // Display fields untouched.
        $this->assertSame('Air Conditioning', $restored->name);
        $this->assertSame('aircon',           $restored->slug);
        $this->assertSame('aircon',           $restored->icon);

        // sort_order stays null — self-heal-on-read at the next active-index
        // visit appends past max_active + 1, landing the restored row at the END
        // of the active list. Restore controller does NOT pre-assign sort_order.
        $this->assertNull($restored->sort_order, 'sort_order stays null; self-heal handles placement at index time');
    }

    public function test_it_re_attaches_pivot_listings_after_restore_cycle(): void
    {
        // End-to-end proof: destroy → restore round-trip preserves the listing's
        // amenities relation. Pivot rows survived destroy (no FK cascade fires
        // on soft delete), and restore re-exposes the amenity so the default
        // scope on Amenity stops hiding it.
        $this->asAdmin();

        $amenity = Amenity::factory()->create();
        $listing = Listing::factory()->create();
        $listing->amenities()->attach($amenity->id);

        // Pre: 1 amenity tagged.
        $this->assertCount(1, $listing->fresh()->amenities);

        $amenity->delete();

        // Mid: default relation hides the trashed amenity.
        $this->assertCount(0, $listing->fresh()->amenities);

        $this->put(route('admin.amenities.restore', $amenity->uuid))
            ->assertRedirect(route('admin.deleted-amenities.index'));

        // Post: relation surfaces the restored amenity again — no manual re-attach.
        $reloaded = $listing->fresh();
        $this->assertCount(1, $reloaded->amenities);
        $this->assertSame($amenity->id, $reloaded->amenities->first()->id);
    }

    public function test_it_silently_ignores_request_body_payload(): void
    {
        // Restore is verb-only — no validation, no $request reads. Attacker
        // payload with display fields or a future deleted_at timestamp must
        // not reach the model. Only deleted_at clears (via SoftDeletes::restore).
        $this->asAdmin();

        $amenity = $this->trashedAmenity([
            'name'       => 'Original Name',
            'slug'       => 'original_slug',
            'icon'       => 'original_slug',
            'sort_order' => null,
        ]);

        $originalUuid = $amenity->uuid;
        $originalId   = $amenity->id;

        $this->put(route('admin.amenities.restore', $amenity->uuid), [
            'name'       => 'HACKED',
            'slug'       => 'hacked',
            'icon'       => 'hacked',
            'sort_order' => 999,
            'id'         => 999999,
            'uuid'       => '019dc5d9-ff77-7381-9755-aaaaaaaaaaaa',
            'deleted_at' => Carbon::parse('2030-01-01 00:00:00')->toDateTimeString(),
        ])->assertRedirect(route('admin.deleted-amenities.index'));

        $restored = Amenity::find($amenity->id);

        // Display fields untouched.
        $this->assertSame('Original Name', $restored->name);
        $this->assertSame('original_slug', $restored->slug);
        $this->assertSame('original_slug', $restored->icon);

        // Identity untouched.
        $this->assertSame($originalId,   $restored->id);
        $this->assertSame($originalUuid, $restored->uuid);

        // sort_order untouched (still null from destroy).
        $this->assertNull($restored->sort_order);

        // deleted_at cleared (the only state mutation).
        $this->assertNull($restored->deleted_at);
    }
}
