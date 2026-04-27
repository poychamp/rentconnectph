<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingCreateTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    public function test_it_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.listings.create'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_returns_view_with_reference_data(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Amenity::factory()->count(3)->create();

        $response = $this->get(route('admin.listings.create'));
        $response->assertOk();

        // amenities — collection of Amenity rows, each with id/name/slug/icon
        // selected. Use array_key_exists against raw attributes — isset() would
        // false-positive on null columns (e.g. icon may be null on factory rows).
        $amenities = $response->viewData('amenities');
        $this->assertCount(3, $amenities);
        $firstAttrs = $amenities->first()->getAttributes();
        foreach (['id', 'name', 'slug', 'icon'] as $field) {
            $this->assertArrayHasKey($field, $firstAttrs, "amenity row missing '{$field}' column");
        }

        // listingTypes — collection of { value, label } enum entries
        $listingTypes = $response->viewData('listingTypes');
        $this->assertNotEmpty($listingTypes);
        $firstType = $listingTypes->first();
        $this->assertArrayHasKey('value', $firstType);
        $this->assertArrayHasKey('label', $firstType);
        $this->assertNotSame($firstType['value'], $firstType['label'], 'enum value and label should differ (label is human-readable)');

        // barangays — same shape as listingTypes
        $barangays = $response->viewData('barangays');
        $this->assertNotEmpty($barangays);
        $firstBarangay = $barangays->first();
        $this->assertArrayHasKey('value', $firstBarangay);
        $this->assertArrayHasKey('label', $firstBarangay);
    }
}
