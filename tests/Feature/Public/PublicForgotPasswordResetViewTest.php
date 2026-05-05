<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class PublicForgotPasswordResetViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_renders_the_reset_password_form_for_guests(): void
    {
        $this->get('/reset-password/some-arbitrary-token?email=admin@example.com')
            ->assertOk();
    }

    public function test_it_redirects_authed_admins_away_from_reset_form(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/reset-password/some-arbitrary-token?email=admin@example.com')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_it_redirects_authed_field_officers_away_from_reset_form(): void
    {
        $field = User::factory()->field()->create();

        $this->actingAs($field, 'admin')
            ->get('/reset-password/some-arbitrary-token?email=admin@example.com')
            ->assertRedirect(route('field.dashboard'));
    }
}
