<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.amenities.index'));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.amenities.index'))->assertForbidden();
    }

    public function test_admin_amenities_index_returns_only_active_amenities_sorted_by_sort_order_with_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Clean monotonic sort_order [3, 1, 2, 4] in insertion order — no duplicates,
        // no nulls — so the self-heal walk is a no-op here. Pure sort assertion.
        // Sorted by (sort_order ASC, id ASC) = [a2 (1), a3 (2), a1 (3), a4 (4)].
        $a1 = Amenity::factory()->create(['name' => 'Alpha',   'sort_order' => 3]);
        $a2 = Amenity::factory()->create(['name' => 'Bravo',   'sort_order' => 1]);
        $a3 = Amenity::factory()->create(['name' => 'Charlie', 'sort_order' => 2]);
        $a4 = Amenity::factory()->create(['name' => 'Delta',   'sort_order' => 4]);

        // Trashed — must NOT appear on the active index.
        $trashed1 = Amenity::factory()->create(['name' => 'TrashedOne', 'sort_order' => 5]);
        $trashed1->delete();
        $trashed2 = Amenity::factory()->create(['name' => 'TrashedTwo', 'sort_order' => 6]);
        $trashed2->delete();

        $response = $this->get(route('admin.amenities.index'));
        $response->assertOk();

        $payload = $response->viewData('amenities');

        $returnedUuids = array_map(fn ($r) => $r['uuid'], $payload);
        $this->assertSame(
            [$a2->uuid, $a3->uuid, $a1->uuid, $a4->uuid],
            $returnedUuids,
            'Active index must order by sort_order ASC, then id ASC'
        );

        $this->assertNotContains($trashed1->uuid, $returnedUuids, 'Trashed amenities must not appear on active index');
        $this->assertNotContains($trashed2->uuid, $returnedUuids);

        $first = $payload[0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'slug', 'icon', 'sort_order', 'created_at', 'updated_at', 'deleted_at'],
            array_keys($first)
        );
        $this->assertNull($first['deleted_at'], 'Active rows must have null deleted_at');

        // Self-heal idempotence: clean fixture means zero writes — sort_order values unchanged.
        $this->assertSame(3, $a1->fresh()->sort_order);
        $this->assertSame(1, $a2->fresh()->sort_order);
        $this->assertSame(2, $a3->fresh()->sort_order);
        $this->assertSame(4, $a4->fresh()->sort_order);
    }

    public function test_admin_amenities_index_self_heals_duplicate_sort_order_drift(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Drift state: two pairs of duplicates. Self-heal walks (sort_order ASC, id ASC):
        //   a1 (sort_order=2) → kept (first occurrence of 2)
        //   a3 (sort_order=2) → irregular (duplicate of 2)
        //   a2 (sort_order=5) → kept (first occurrence of 5)
        //   a4 (sort_order=5) → irregular (duplicate of 5)
        // After repair: a1 stays 2, a2 stays 5, a3 → 6 (max+1+0), a4 → 7 (max+1+1).
        $a1 = Amenity::factory()->create(['name' => 'Alpha',   'sort_order' => 2]);
        $a2 = Amenity::factory()->create(['name' => 'Bravo',   'sort_order' => 5]);
        $a3 = Amenity::factory()->create(['name' => 'Charlie', 'sort_order' => 2]);
        $a4 = Amenity::factory()->create(['name' => 'Delta',   'sort_order' => 5]);

        $response = $this->get(route('admin.amenities.index'));
        $response->assertOk();

        // DB-side repair: lower-id duplicates kept, higher-id duplicates appended past max.
        $this->assertSame(2, $a1->fresh()->sort_order, 'a1 keeps sort_order=2 (first occurrence)');
        $this->assertSame(5, $a2->fresh()->sort_order, 'a2 keeps sort_order=5 (first occurrence)');
        $this->assertSame(6, $a3->fresh()->sort_order, 'a3 (duplicate of 2) bumped to max+1');
        $this->assertSame(7, $a4->fresh()->sort_order, 'a4 (duplicate of 5) bumped to max+2');

        // Returned order: kept rows first (already sorted), irregulars appended in insertion order.
        $returnedUuids = array_map(fn ($r) => $r['uuid'], $response->viewData('amenities'));
        $this->assertSame(
            [$a1->uuid, $a2->uuid, $a3->uuid, $a4->uuid],
            $returnedUuids,
            'Self-heal returns kept rows first then appended irregulars in insertion order'
        );

        // Idempotence: a second GET with no drift should not re-write anything.
        $a1Updated = $a1->fresh()->updated_at;
        $a3Updated = $a3->fresh()->updated_at;

        $this->get(route('admin.amenities.index'))->assertOk();

        $this->assertEquals($a1Updated, $a1->fresh()->updated_at, 'No drift → no DB write on a1');
        $this->assertEquals($a3Updated, $a3->fresh()->updated_at, 'No drift → no DB write on a3');
    }

    public function test_admin_amenities_index_returns_active_and_deleted_counts_for_tab_badges(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Amenity::factory()->count(4)->create();

        $trashed1 = Amenity::factory()->create();
        $trashed1->delete();
        $trashed2 = Amenity::factory()->create();
        $trashed2->delete();

        $response = $this->get(route('admin.amenities.index'));
        $response->assertOk();

        $counts = $response->viewData('counts');
        $this->assertSame(4, $counts['active'],  'Active count must reflect non-trashed rows');
        $this->assertSame(2, $counts['deleted'], 'Deleted count must reflect trashed rows');
    }
}
