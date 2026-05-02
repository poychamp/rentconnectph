<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityCreateViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.amenities.create'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.amenities.create'))->assertForbidden();
    }

    public function test_it_returns_view_with_200_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('admin.amenities.create'));

        $response->assertOk();
    }
}
