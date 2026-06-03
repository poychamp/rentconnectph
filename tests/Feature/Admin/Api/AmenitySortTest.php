<?php

namespace Tests\Feature\Admin\Api;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AmenitySortTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_returns_401_for_guest(): void
    {
        $response = $this->putJson(route('admin.api.amenities.sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $response = $this->putJson(route('admin.api.amenities.sort'), [
            'order' => ['00000000-0000-0000-0000-000000000000'],
        ]);

        $response->assertForbidden();
    }

    public function test_it_validates_order_payload(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Missing order key → 422.
        $this->putJson(route('admin.api.amenities.sort'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        // Empty array → 422 (rule is required|array|min:1).
        $this->putJson(route('admin.api.amenities.sort'), ['order' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        // Non-UUID string → 422 (rule is string|uuid per item).
        $this->putJson(route('admin.api.amenities.sort'), ['order' => ['not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order.0']);
    }

    public function test_it_rejects_payload_when_any_uuid_is_unknown_or_trashed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $active1 = Amenity::factory()->create(['sort_order' => 1]);
        $active2 = Amenity::factory()->create(['sort_order' => 2]);

        $trashed = Amenity::factory()->create(['sort_order' => 3]);
        $trashed->delete();

        // A trashed UUID in the order — count mismatch trips the slice guard.
        $this->putJson(route('admin.api.amenities.sort'), [
            'order' => [$active1->uuid, $trashed->uuid, $active2->uuid],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        // A completely fabricated UUID — same 422 path.
        $this->putJson(route('admin.api.amenities.sort'), [
            'order' => [$active1->uuid, '11111111-1111-1111-1111-111111111111'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);

        // Confirm DB untouched after both rejection paths.
        $this->assertSame(1, $active1->fresh()->sort_order);
        $this->assertSame(2, $active2->fresh()->sort_order);
    }

    public function test_it_persists_new_order_and_writes_sequential_integers_starting_at_1(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Initial sort_order values [10, 20, 30, 40] so we know nothing accidentally
        // matches the post-PUT expected values [1, 2, 3, 4].
        $a = Amenity::factory()->create(['sort_order' => 10]);
        $b = Amenity::factory()->create(['sort_order' => 20]);
        $c = Amenity::factory()->create(['sort_order' => 30]);
        $d = Amenity::factory()->create(['sort_order' => 40]);

        // Reorder: [d, b, c, a]. Expected post-PUT sort_order: d=1, b=2, c=3, a=4.
        $response = $this->putJson(route('admin.api.amenities.sort'), [
            'order' => [$d->uuid, $b->uuid, $c->uuid, $a->uuid],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertSame(1, $d->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
        $this->assertSame(3, $c->fresh()->sort_order);
        $this->assertSame(4, $a->fresh()->sort_order);

        // Reverse the reorder: [a, c, b, d]. Expected: a=1, c=2, b=3, d=4.
        $this->putJson(route('admin.api.amenities.sort'), [
            'order' => [$a->uuid, $c->uuid, $b->uuid, $d->uuid],
        ])->assertOk();

        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
        $this->assertSame(4, $d->fresh()->sort_order);
    }
}
