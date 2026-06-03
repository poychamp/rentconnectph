<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminAmenityUpdateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    private function asAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Air Conditioning',
            'slug' => 'aircon',
        ], $overrides);
    }

    public function test_it_redirects_guest_to_login(): void
    {
        $amenity = Amenity::factory()->create();

        $this->put(route('admin.amenities.update', $amenity->uuid), $this->validPayload())
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_user_lacking_amenities_manage_permission(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $amenity = Amenity::factory()->create();

        $this->put(route('admin.amenities.update', $amenity->uuid), $this->validPayload())
            ->assertForbidden();
    }

    public function test_it_returns_404_for_unknown_uuid(): void
    {
        $this->asAdmin();

        $this->put(route('admin.amenities.update', '019dc5d9-ff77-7381-9755-000000000000'), $this->validPayload())
            ->assertNotFound();
    }

    public function test_it_returns_404_for_soft_deleted_amenity(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create();
        $amenity->delete();

        $this->put(route('admin.amenities.update', $amenity->uuid), $this->validPayload())
            ->assertNotFound();
    }

    public function test_it_persists_updates_and_redirects_to_index_on_success(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create([
            'name'       => 'Old Name',
            'slug'       => 'old_slug',
            'icon'       => 'old_slug',
            'sort_order' => 5,
        ]);

        $response = $this->put(route('admin.amenities.update', $amenity->uuid), [
            'name' => 'Air Conditioning',
            'slug' => 'aircon',
        ]);

        $response->assertRedirect(route('admin.amenities.index'));
        $response->assertSessionHas('success');

        $fresh = $amenity->fresh();
        $this->assertSame('Air Conditioning', $fresh->name);
        $this->assertSame('aircon',           $fresh->slug);
        $this->assertSame('aircon',           $fresh->icon, 'icon must re-derive from slug on update');
        $this->assertSame(5,                  $fresh->sort_order, 'sort_order is owned by drag-reorder endpoint, not update');
    }

    public function test_it_validates_required_fields(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create([
            'name' => 'Original',
            'slug' => 'original_slug',
            'icon' => 'original_slug',
        ]);

        // Empty payload — both name and slug missing.
        $this->put(route('admin.amenities.update', $amenity->uuid), [])
            ->assertSessionHasErrors([
                'name' => 'Name is required.',
                'slug' => 'Slug is required.',
            ]);

        // Missing only name.
        $this->put(route('admin.amenities.update', $amenity->uuid), ['slug' => 'whatever'])
            ->assertSessionHasErrors(['name' => 'Name is required.'])
            ->assertSessionDoesntHaveErrors('slug');

        // Missing only slug.
        $this->put(route('admin.amenities.update', $amenity->uuid), ['name' => 'Whatever'])
            ->assertSessionHasErrors(['slug' => 'Slug is required.'])
            ->assertSessionDoesntHaveErrors('name');

        // None of the failed attempts touched the persisted row.
        $fresh = $amenity->fresh();
        $this->assertSame('Original',      $fresh->name);
        $this->assertSame('original_slug', $fresh->slug);
        $this->assertSame('original_slug', $fresh->icon);
    }

    public function test_it_rejects_slug_violating_snake_case_regex(): void
    {
        $this->asAdmin();

        $amenity = Amenity::factory()->create([
            'name' => 'Original',
            'slug' => 'original_slug',
            'icon' => 'original_slug',
        ]);

        $expectedSlugError = 'Slug must be snake_case (lowercase letters, digits, underscores; starts with a letter).';

        $invalidSlugs = [
            'wi-fi',  // hyphen
            'WiFi',   // uppercase
            '5g',     // starts with digit
            'wi fi',  // space
        ];

        foreach ($invalidSlugs as $slug) {
            $this->put(route('admin.amenities.update', $amenity->uuid), [
                'name' => 'Whatever',
                'slug' => $slug,
            ])->assertSessionHasErrors(['slug' => $expectedSlugError]);
        }

        // Persisted row untouched.
        $fresh = $amenity->fresh();
        $this->assertSame('Original',      $fresh->name);
        $this->assertSame('original_slug', $fresh->slug);
    }

    public function test_it_allows_updating_with_unchanged_slug(): void
    {
        $this->asAdmin();

        // Common trap: the unique rule must ignore the row's own id, otherwise
        // a name-only edit would falsely fail with "Slug already exists."
        $amenity = Amenity::factory()->create([
            'name' => 'Old Name',
            'slug' => 'unchanged',
            'icon' => 'unchanged',
        ]);

        $this->put(route('admin.amenities.update', $amenity->uuid), [
            'name' => 'New Display Name',
            'slug' => 'unchanged',
        ])->assertRedirect(route('admin.amenities.index'))
          ->assertSessionDoesntHaveErrors('slug');

        $fresh = $amenity->fresh();
        $this->assertSame('New Display Name', $fresh->name);
        $this->assertSame('unchanged',        $fresh->slug);
    }

    public function test_it_allows_updating_with_same_name_and_same_slug(): void
    {
        $this->asAdmin();

        // No-op submit: admin opens Edit, hits Update without typing anything.
        // Must not false-fail on the unique slug rule (self-uniqueness) and must
        // not error on the validation layer for "no changes detected" or similar.
        $amenity = Amenity::factory()->create([
            'name' => 'Air Conditioning',
            'slug' => 'aircon',
            'icon' => 'aircon',
        ]);

        $this->put(route('admin.amenities.update', $amenity->uuid), [
            'name' => 'Air Conditioning',
            'slug' => 'aircon',
        ])->assertRedirect(route('admin.amenities.index'))
          ->assertSessionDoesntHaveErrors(['name', 'slug']);

        $fresh = $amenity->fresh();
        $this->assertSame('Air Conditioning', $fresh->name);
        $this->assertSame('aircon',           $fresh->slug);
        $this->assertSame('aircon',           $fresh->icon);
    }

    public function test_it_rejects_slug_already_taken_by_another_active_amenity(): void
    {
        $this->asAdmin();

        Amenity::factory()->create([
            'name' => 'Sibling',
            'slug' => 'taken_slug',
            'icon' => 'taken_slug',
        ]);

        $target = Amenity::factory()->create([
            'name' => 'Target',
            'slug' => 'target_slug',
            'icon' => 'target_slug',
        ]);

        $this->put(route('admin.amenities.update', $target->uuid), [
            'name' => 'Target',
            'slug' => 'taken_slug',
        ])->assertSessionHasErrors(['slug' => 'Slug already exists.']);

        // Persisted slug unchanged.
        $this->assertSame('target_slug', $target->fresh()->slug);
    }

    public function test_it_allows_slug_already_held_by_a_soft_deleted_row(): void
    {
        $this->asAdmin();

        // Trashed sibling with the slug we'll claim.
        $trashed = Amenity::factory()->create([
            'name' => 'Old Sibling',
            'slug' => 'reclaim_me',
            'icon' => 'reclaim_me',
        ]);
        $trashed->delete();

        $target = Amenity::factory()->create([
            'name' => 'Target',
            'slug' => 'target_slug',
            'icon' => 'target_slug',
        ]);

        $this->put(route('admin.amenities.update', $target->uuid), [
            'name' => 'Target',
            'slug' => 'reclaim_me',
        ])->assertRedirect(route('admin.amenities.index'));

        $fresh = $target->fresh();
        $this->assertSame('reclaim_me', $fresh->slug);
        $this->assertSame('reclaim_me', $fresh->icon);

        // Trashed row still trashed, slug intact.
        $stillTrashed = Amenity::onlyTrashed()->find($trashed->id);
        $this->assertNotNull($stillTrashed);
        $this->assertSame('reclaim_me', $stillTrashed->slug);
    }

    public function test_it_silently_ignores_read_only_fields_in_request(): void
    {
        $this->asAdmin();

        $createdAt = Carbon::parse('2026-04-01 10:00:00');

        $amenity = Amenity::factory()->create([
            'name'       => 'Original',
            'slug'       => 'original_slug',
            'icon'       => 'original_slug',
            'sort_order' => 7,
            'created_at' => $createdAt,
        ]);

        $originalUuid = $amenity->uuid;
        $originalId   = $amenity->id;

        // Attacker payload: fields owned by other surfaces (drag-reorder for
        // sort_order, server-derive for icon, immutable for id/uuid/timestamps).
        $this->put(route('admin.amenities.update', $amenity->uuid), [
            'name'       => 'New Name',
            'slug'       => 'new_slug',
            'sort_order' => 999,
            'icon'       => 'attacker_icon',
            'id'         => 999999,
            'uuid'       => '019dc5d9-ff77-7381-9755-aaaaaaaaaaaa',
            'created_at' => Carbon::parse('1990-01-01 00:00:00')->toDateTimeString(),
            'deleted_at' => Carbon::parse('1990-01-01 00:00:00')->toDateTimeString(),
        ])->assertRedirect(route('admin.amenities.index'));

        $fresh = $amenity->fresh();

        // Editable fields persisted.
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('new_slug', $fresh->slug);
        $this->assertSame('new_slug', $fresh->icon, 'icon re-derives from slug, not from attacker payload');

        // Read-only fields untouched.
        $this->assertSame($originalId,   $fresh->id);
        $this->assertSame($originalUuid, $fresh->uuid);
        $this->assertSame(7,             $fresh->sort_order, 'sort_order is owned by drag-reorder endpoint');
        $this->assertEquals($createdAt->toDateTimeString(), $fresh->created_at->toDateTimeString());
        $this->assertNull($fresh->deleted_at);
    }
}
