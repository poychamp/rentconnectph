<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityDeletedViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $this->get(route('admin.deleted-amenities.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.deleted-amenities.index'))
            ->assertForbidden();
    }

    public function test_it_returns_only_soft_deleted_amenities_sorted_by_deleted_at_desc_with_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Three trashed rows with non-monotonic deleted_at — proves the sort
        // isn't an accidental insertion-order or id-based artifact.
        $a1 = Amenity::factory()->create(['name' => 'Alpha',   'sort_order' => 1]);
        $a1->deleted_at = Carbon::parse('2026-04-01 10:00:00');
        $a1->save();

        $a2 = Amenity::factory()->create(['name' => 'Bravo',   'sort_order' => 2]);
        $a2->deleted_at = Carbon::parse('2026-04-30 10:00:00');
        $a2->save();

        $a3 = Amenity::factory()->create(['name' => 'Charlie', 'sort_order' => 3]);
        $a3->deleted_at = Carbon::parse('2026-04-15 10:00:00');
        $a3->save();

        $response = $this->get(route('admin.deleted-amenities.index'));
        $response->assertOk();

        $payload = $response->viewData('amenities');

        // Sort: deleted_at DESC → [a2 (Apr 30), a3 (Apr 15), a1 (Apr 1)].
        $returnedUuids = array_map(fn ($r) => $r['uuid'], $payload);
        $this->assertSame(
            [$a2->uuid, $a3->uuid, $a1->uuid],
            $returnedUuids,
            'Deleted index must order by deleted_at DESC, then id DESC'
        );

        // Resource shape — same 9 keys as the Active index (AdminAmenityResource).
        $first = $payload[0];
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'slug', 'icon', 'sort_order', 'created_at', 'updated_at', 'deleted_at'],
            array_keys($first)
        );
        $this->assertNotNull($first['deleted_at'], 'Trashed rows must have non-null deleted_at');
    }

    public function test_it_excludes_active_amenities_from_the_deleted_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $active1 = Amenity::factory()->create(['name' => 'ActiveOne']);
        $active2 = Amenity::factory()->create(['name' => 'ActiveTwo']);

        $trashed = Amenity::factory()->create(['name' => 'TrashedOne']);
        $trashed->delete();

        $response = $this->get(route('admin.deleted-amenities.index'));
        $response->assertOk();

        $payload = $response->viewData('amenities');
        $returnedUuids = array_map(fn ($r) => $r['uuid'], $payload);

        $this->assertNotContains($active1->uuid, $returnedUuids, 'Active rows must NOT appear on the Deleted index');
        $this->assertNotContains($active2->uuid, $returnedUuids);
        $this->assertContains($trashed->uuid, $returnedUuids);
        $this->assertCount(1, $returnedUuids);
    }

    public function test_it_returns_active_and_deleted_counts_for_tab_badges(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Amenity::factory()->count(4)->create();

        $trashed1 = Amenity::factory()->create();
        $trashed1->delete();
        $trashed2 = Amenity::factory()->create();
        $trashed2->delete();
        $trashed3 = Amenity::factory()->create();
        $trashed3->delete();

        $response = $this->get(route('admin.deleted-amenities.index'));
        $response->assertOk();

        $counts = $response->viewData('counts');
        $this->assertSame(4, $counts['active'],  'Active count must reflect non-trashed rows');
        $this->assertSame(3, $counts['deleted'], 'Deleted count must reflect trashed rows');
    }
}
