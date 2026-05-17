<?php

namespace Tests\Feature\Admin\Api;

use App\Models\ListingContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminApiListingContactsFindByPhoneTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // =========================================================================
    // Auth + access (2)
    // =========================================================================

    public function test_it_rejects_guest(): void
    {
        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '+639171234567']))
             ->assertUnauthorized();
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '+639171234567']))
             ->assertForbidden();
    }

    // =========================================================================
    // Lookup behavior (6)
    // =========================================================================

    public function test_it_returns_existing_contact_when_phone_matches(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $contact = ListingContact::factory()->create([
            'phone' => '+639171234567',
            'name'  => 'Maria Reyes',
            'notes' => 'Philhomes broker',
        ]);

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '+639171234567']))
             ->assertOk()
             ->assertExactJson([
                 'contact' => [
                     'uuid'  => $contact->uuid,
                     'name'  => 'Maria Reyes',
                     'phone' => '+639171234567',
                     'notes' => 'Philhomes broker',
                 ],
             ]);
    }

    public function test_it_normalizes_phone_before_lookup(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Stored in canonical E.164; admin types local form on the call.
        ListingContact::factory()->create(['phone' => '+639171234567']);

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '09171234567']))
             ->assertOk()
             ->assertJsonPath('contact.phone', '+639171234567');
    }

    public function test_it_returns_null_contact_when_phone_does_not_match(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '+639991111111']))
             ->assertOk()
             ->assertExactJson(['contact' => null]);
    }

    public function test_it_returns_null_contact_when_phone_missing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson(route('admin.api.listing-contacts.find-by-phone'))
             ->assertOk()
             ->assertExactJson(['contact' => null]);
    }

    public function test_it_returns_null_contact_when_phone_invalid_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => 'not-a-phone']))
             ->assertOk()
             ->assertExactJson(['contact' => null]);
    }

    public function test_it_excludes_soft_deleted_contacts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $contact = ListingContact::factory()->create(['phone' => '+639171234567']);
        $contact->delete();

        $this->getJson(route('admin.api.listing-contacts.find-by-phone', ['phone' => '+639171234567']))
             ->assertOk()
             ->assertExactJson(['contact' => null]);
    }
}
