<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityEditViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $amenity = Amenity::factory()->create();

        $this->get(route('admin.amenities.edit', $amenity->uuid))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $amenity = Amenity::factory()->create();

        $this->get(route('admin.amenities.edit', $amenity->uuid))
            ->assertForbidden();
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.amenities.edit', '019dc5d9-ff77-7381-9755-000000000000'))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_amenity(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $amenity = Amenity::factory()->create();
        $amenity->delete();

        $this->get(route('admin.amenities.edit', $amenity->uuid))
            ->assertNotFound();
    }

    public function test_it_returns_view_with_200_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $amenity = Amenity::factory()->create();

        $this->get(route('admin.amenities.edit', $amenity->uuid))
            ->assertOk();
    }

    public function test_it_returns_amenity_payload_with_expected_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $amenity = Amenity::factory()->create([
            'name'       => 'Air Conditioning',
            'slug'       => 'aircon',
            'icon'       => 'aircon',
            'sort_order' => 3,
        ]);

        $response = $this->get(route('admin.amenities.edit', $amenity->uuid));
        $response->assertOk();

        $payload = $response->viewData('amenity');

        $this->assertIsArray($payload);

        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'name', 'slug', 'icon', 'sort_order', 'created_at', 'updated_at', 'deleted_at'],
            array_keys($payload)
        );

        $this->assertSame($amenity->id,   $payload['id']);
        $this->assertSame($amenity->uuid, $payload['uuid']);
        $this->assertSame('Air Conditioning', $payload['name']);
        $this->assertSame('aircon',           $payload['slug']);
        $this->assertSame('aircon',           $payload['icon']);
        $this->assertSame(3,                  $payload['sort_order']);
        $this->assertNull($payload['deleted_at']);
    }
}
