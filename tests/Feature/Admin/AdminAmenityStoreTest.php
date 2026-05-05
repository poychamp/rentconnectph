<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityStoreTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->post(route('admin.amenities.store'), [
            'name' => 'WiFi',
            'slug' => 'wifi',
        ]);

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->post(route('admin.amenities.store'), [
            'name' => 'WiFi',
            'slug' => 'wifi',
        ])->assertForbidden();
    }

    public function test_it_persists_a_new_amenity_and_redirects_to_index_on_success(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->post(route('admin.amenities.store'), [
            'name' => 'Generator Backup',
            'slug' => 'generator_backup',
        ]);

        $response->assertRedirect(route('admin.amenities.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('amenities', 1);

        $created = Amenity::first();
        $this->assertSame('Generator Backup', $created->name);
        $this->assertSame('generator_backup', $created->slug);
        $this->assertSame('generator_backup', $created->icon, 'icon mirrors slug — server-derived');
        $this->assertNull($created->sort_order, 'sort_order persists null at create — self-heal at index time repairs it');
    }

    public function test_it_validates_required_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Empty payload — both name and slug missing.
        $this->post(route('admin.amenities.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Name is required.',
                'slug' => 'Slug is required.',
            ]);

        // Missing only name.
        $this->post(route('admin.amenities.store'), ['slug' => 'wifi'])
            ->assertSessionHasErrors(['name' => 'Name is required.'])
            ->assertSessionDoesntHaveErrors('slug');

        // Missing only slug.
        $this->post(route('admin.amenities.store'), ['name' => 'WiFi'])
            ->assertSessionHasErrors(['slug' => 'Slug is required.'])
            ->assertSessionDoesntHaveErrors('name');

        // Nothing got created from any of the failed attempts.
        $this->assertDatabaseCount('amenities', 0);
    }

    public function test_it_rejects_slug_violating_snake_case_regex(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $expectedSlugError = 'Slug must be snake_case (lowercase letters, digits, underscores; starts with a letter).';

        $invalidSlugs = [
            'wi-fi',  // hyphen
            'WiFi',   // uppercase
            '5g',     // starts with digit
            'wi fi',  // space
        ];

        foreach ($invalidSlugs as $slug) {
            $this->post(route('admin.amenities.store'), [
                'name' => 'Whatever',
                'slug' => $slug,
            ])->assertSessionHasErrors(['slug' => $expectedSlugError]);
        }

        $this->assertDatabaseCount('amenities', 0);
    }

    public function test_it_handles_slug_uniqueness_correctly(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Seed an active amenity claiming slug 'wifi'.
        $existing = Amenity::factory()->create([
            'name' => 'WiFi',
            'slug' => 'wifi',
        ]);

        // Active duplicate — must reject with the unique-slug message.
        $this->post(route('admin.amenities.store'), [
            'name' => 'Wi-Fi',
            'slug' => 'wifi',
        ])->assertSessionHasErrors(['slug' => 'Slug already exists.']);

        $this->assertDatabaseCount('amenities', 1);

        // Soft-delete the original. Now the slug is "claimed" only by a trashed
        // row, so a new amenity with the same slug should be allowed per
        // CLAUDE.md's no-DB-UNIQUE + Rule::unique(...)->whereNull('deleted_at')
        // convention.
        $existing->delete();

        $this->post(route('admin.amenities.store'), [
            'name' => 'Wi-Fi',
            'slug' => 'wifi',
        ])->assertRedirect(route('admin.amenities.index'));

        // Two rows now: original soft-deleted, new one active.
        $this->assertSame(1, Amenity::count());
        $this->assertSame(2, Amenity::withTrashed()->count());

        $newRow = Amenity::where('slug', 'wifi')->first();
        $this->assertNotSame($existing->id, $newRow->id);
        $this->assertSame('Wi-Fi', $newRow->name);

        $stillTrashed = Amenity::onlyTrashed()->find($existing->id);
        $this->assertNotNull($stillTrashed);
    }

}
