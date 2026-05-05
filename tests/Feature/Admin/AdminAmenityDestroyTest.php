<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityDestroyTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $amenity = Amenity::factory()->create();

        $this->delete(route('admin.amenities.destroy', $amenity->uuid))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $amenity = Amenity::factory()->create();

        $this->delete(route('admin.amenities.destroy', $amenity->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $this->asAdmin();

        $this->delete(route('admin.amenities.destroy', '019dc5d9-ff77-7381-9755-000000000000'))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_already_soft_deleted_amenity(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create();
        $amenity->delete();

        $this->delete(route('admin.amenities.destroy', $amenity->uuid))
            ->assertNotFound();
    }

    public function test_it_soft_deletes_the_amenity_and_redirects_to_index_on_success(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create([
            'name'       => 'Air Conditioning',
            'slug'       => 'aircon',
            'icon'       => 'aircon',
            'sort_order' => 5,
        ]);

        $response = $this->delete(route('admin.amenities.destroy', $amenity->uuid));

        $response->assertRedirect(route('admin.amenities.index'));
        $response->assertSessionHas('success');

        // Default scope filters it out — must be invisible to active queries.
        $this->assertNull(Amenity::find($amenity->id), 'Soft-deleted row must not surface via the default scope');

        // But the row still exists with deleted_at set — Restore can still reach it.
        $trashed = Amenity::onlyTrashed()->find($amenity->id);
        $this->assertNotNull($trashed, 'Row must remain in DB with deleted_at set, NOT hard-deleted');
        $this->assertNotNull($trashed->deleted_at);

        // Display fields preserved — name, slug, icon still rendered on the Deleted tab.
        $this->assertSame('Air Conditioning', $trashed->name);
        $this->assertSame('aircon',           $trashed->slug);
        $this->assertSame('aircon',           $trashed->icon);

        // sort_order clears on destroy. self-heal-on-read at the active index
        // appends null sort_order rows past max_active + 1, so a future restore
        // lands at the END of the active list — never inserts back into the
        // middle and disrupts the admin's current order.
        $this->assertNull($trashed->sort_order, 'sort_order must clear on destroy for restore-to-end semantics');
    }

    public function test_it_preserves_pivot_attachments_on_soft_delete(): void
    {
        $this->asAdmin();

        // Tag a listing with the amenity, then soft-delete the amenity.
        // Pivot rows must survive — soft delete doesn't trigger FK cascades.
        // This is load-bearing for the future Restore flow: when admin restores
        // the amenity, listings reattach automatically (the pivot was never gone).
        $amenity = Amenity::factory()->create();
        $listing = Listing::factory()->create();
        $listing->amenities()->attach($amenity->id);

        $this->assertSame(1, DB::table('amenity_listing')
            ->where('amenity_id', $amenity->id)
            ->where('listing_id', $listing->id)
            ->count());

        $this->delete(route('admin.amenities.destroy', $amenity->uuid))
            ->assertRedirect(route('admin.amenities.index'));

        // Pivot row still there.
        $this->assertSame(1, DB::table('amenity_listing')
            ->where('amenity_id', $amenity->id)
            ->where('listing_id', $listing->id)
            ->count(), 'Pivot row must survive soft delete (no cascade fires)');

        // Default amenities() relation hides the trashed amenity (Eloquent
        // default scope on the Amenity model filters trashed).
        $this->assertCount(0, $listing->fresh()->amenities, 'Default relation hides trashed amenities');
    }
}
