<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AuthProfileViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('auth.profile.show'));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_renders_for_authed_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user, 'admin')->get(route('auth.profile.show'));

        $response->assertOk();
        $response->assertViewIs('auth.profile');
    }

    public function test_it_renders_for_authed_field_officer(): void
    {
        $user = User::factory()->field()->create();

        $response = $this->actingAs($user, 'admin')->get(route('auth.profile.show'));

        $response->assertOk();
        $response->assertViewIs('auth.profile');
    }
}
