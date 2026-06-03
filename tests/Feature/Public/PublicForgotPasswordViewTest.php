<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class PublicForgotPasswordViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_renders_the_forgot_password_form_for_guests(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_it_redirects_authed_admins_away_from_request_form(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/forgot-password')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_it_redirects_authed_field_officers_away_from_request_form(): void
    {
        $field = User::factory()->field()->create();

        $this->actingAs($field, 'admin')
            ->get('/forgot-password')
            ->assertRedirect(route('field.dashboard'));
    }
}
