<?php

namespace Tests\Feature\Admin;

use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminListingUnverifiedEditTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // =========================================================================
    // Auth + access (1)
    // =========================================================================

    public function test_it_redirects_guest_to_login(): void
    {
        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]))->assertRedirect(route('auth.login'));
    }

    // =========================================================================
    // Resolution + slice guard (2)
    // =========================================================================

    public function test_it_returns_404_for_unknown_or_soft_deleted_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Nonexistent UUID
        $this->get(route('admin.listings.edit', [
            'listing' => '00000000-0000-0000-0000-000000000000',
            'from'    => 'unverified',
        ]))->assertNotFound();

        // Soft-deleted listing — route-model binding's SoftDeletes scope hides it
        $deleted = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);
        $deleted->delete();

        $this->get(route('admin.listings.edit', [
            'listing' => $deleted->uuid,
            'from'    => 'unverified',
        ]))->assertNotFound();
    }

    public function test_it_returns_404_when_listing_is_verified_and_from_is_unverified(): void
    {
        // Slice mismatch protection — admin lands on /edit?from=unverified for
        // a verified listing (stale URL, race, or mistake). Don't render the
        // unverified-flow UI on a verified row; 404 instead.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $verified = Listing::factory()->create([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $this->get(route('admin.listings.unverified-edit', [
            'listing' => $verified->uuid,
        ]))->assertNotFound();
    }

    // =========================================================================
    // View data — listing payload shape (1)
    // =========================================================================

    public function test_it_returns_listing_payload_with_queue_and_source_fields(): void
    {
        // Per FRD-023 § 3.9, the unverified slice's `listing` view-var is an
        // ARRAY (not a Model) with the queue + source + contact fields the
        // calls-team UX needs. Decoupling from the Model lets the controller
        // shape the payload conditionally on slice without leaking model state.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'title'          => 'Spotted on OLX',
            'is_verified'    => false,
            'verified_at'    => null,
            'prequal_status' => 'called_yes',
            'queue_status'   => 'unassigned',
            'source_site'    => 'olx',
            'source_url'     => 'https://www.olx.ph/item/sample-12345',
            'contact_phone'  => '+639171234567',
        ]);

        $response = $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]));
        $response->assertOk();

        $payload = $response->viewData('listing');
        $this->assertIsArray($payload, 'Unverified-slice listing payload must be an array, not a Model');

        // Core shared fields
        $this->assertSame($listing->uuid,  $payload['uuid']);
        $this->assertSame('Spotted on OLX', $payload['title']);

        // Queue state
        $this->assertSame('called_yes', $payload['prequal_status']);
        $this->assertSame('unassigned', $payload['queue_status']);

        // Source + contact
        $this->assertSame('olx',                                   $payload['source_site']);
        $this->assertSame('https://www.olx.ph/item/sample-12345', $payload['source_url']);
        $this->assertSame('+639171234567',                         $payload['contact_phone']);
    }

    public function test_it_exposes_call_context_keys_in_listing_payload(): void
    {
        // Per FRD-023 § 3.9, the unverified slice's listing payload exposes the
        // call-context keys so the Edit page can hydrate the call-context card
        // form. Values stay null until admin saves them via update() — round-
        // trip-with-values is covered by the update-test slice when that lands.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $response = $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]));
        $response->assertOk();

        $payload = $response->viewData('listing');

        $this->assertArrayHasKey('directions',         $payload);
        $this->assertArrayHasKey('contact_type',       $payload);
        $this->assertArrayHasKey('verification_notes', $payload);
        $this->assertArrayHasKey('assigned_to',        $payload);
    }

    // =========================================================================
    // View data — enum + role lists (3)
    // =========================================================================

    public function test_it_includes_field_users_list_with_id_and_name_only(): void
    {
        // Per FRD-023 § 3.9, `field_users` is the assignable-officers dropdown
        // population for the called_yes assign flow. Spatie role-scoped query
        // (`User::role('field')`) — only users WITH the field role appear.
        // Resource shape: [{id, name}] only — no email leakage to frontend.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified'    => false,
            'verified_at'    => null,
            'prequal_status' => 'called_yes',
        ]);

        // Two field officers — sorted by name in the payload
        $maria  = User::factory()->create(['name' => 'Maria Cruz']);
        $andres = User::factory()->create(['name' => 'Andres Reyes']);
        $maria->assignRole('field');
        $andres->assignRole('field');

        // Non-field admin — must NOT appear in field_users
        User::factory()->superAdmin()->create(['name' => 'Some Admin']);

        $response = $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]));

        $fieldUsers = $response->viewData('fieldUsers');
        $this->assertIsArray($fieldUsers);
        $this->assertCount(2, $fieldUsers, 'Only role=field users should appear');

        $names = array_column($fieldUsers, 'name');
        $this->assertSame(['Andres Reyes', 'Maria Cruz'], $names, 'Sorted by name ASC');

        foreach ($fieldUsers as $u) {
            $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($u),
                'field_users entries leak fields beyond id+name');
        }
    }

    public function test_it_includes_contact_types_enum_list(): void
    {
        // Per FRD-023 § 3.9, the called_yes assign form needs a select
        // populated with ContactType options (owner / authorized_rep / broker /
        // caretaker). Server passes the [{value, label}] list so Vue doesn't
        // hardcode the enum.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $response = $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]));

        $contactTypes = $response->viewData('contactTypes');
        $this->assertIsArray($contactTypes);

        $values = array_column($contactTypes, 'value');
        $labels = array_column($contactTypes, 'label');

        $this->assertSame(['owner', 'authorized_rep', 'broker', 'caretaker', 'others'], $values);
        $this->assertSame(['Owner', 'Authorized Rep', 'Broker', 'Caretaker', 'Others'], $labels);
    }

    public function test_it_includes_source_sites_enum_list(): void
    {
        // Source-site dropdown population. Server passes the [{value, label}]
        // list — already exposed today, but reaffirming the contract on the
        // unverified slice.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->create([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $response = $this->get(route('admin.listings.unverified-edit', [
            'listing' => $listing->uuid,
        ]));

        $sourceSites = $response->viewData('sourceSites');
        $this->assertIsArray($sourceSites);

        $values = array_column($sourceSites, 'value');
        $this->assertSame(
            ['olx', 'rent_ph', 'lamudi', 'facebook_group', 'facebook_marketplace', 'field_discovery', 'referral', 'others'],
            $values,
        );
    }
}
