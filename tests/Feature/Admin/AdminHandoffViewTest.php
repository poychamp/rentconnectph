<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactType;
use App\Models\HandoffLock;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminHandoffViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $response = $this->get(route('admin.handoffs.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.handoffs.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_it_loads_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        HandoffLock::factory()->create();

        $response = $this->get(route('admin.handoffs.index'));

        $response->assertOk();
        $response->assertViewIs('admin.handoffs.index');

        $this->assertCount(1, $response->viewData('handoffs')['data']);
    }

    // ---------------------------------------------------------------------
    // Sort — created_at ASC (oldest = release candidate first).
    // Shuffled creation order defeats the autoincrement-id explanation;
    // explicit createdAt() state writes deterministic times since
    // HandoffLock uses DB useCurrent() (insensitive to Carbon::setTestNow).
    // ---------------------------------------------------------------------

    public function test_it_orders_by_created_at_asc(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $base = Carbon::create(2026, 5, 1, 12, 0, 0);

        $middle = HandoffLock::factory()->createdAt($base->copy()->addDay())->create();
        $newest = HandoffLock::factory()->createdAt($base->copy()->addDays(2))->create();
        $oldest = HandoffLock::factory()->createdAt($base)->create();

        $response = $this->get(route('admin.handoffs.index'));
        $response->assertOk();

        $ids = array_map(fn ($r) => $r['id'], $response->viewData('handoffs')['data']);

        $this->assertSame(
            [$oldest->id, $middle->id, $newest->id],
            $ids,
            'Handoffs index must order by created_at ASC — oldest = release candidate first.',
        );
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    public function test_it_paginates_at_10_per_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        HandoffLock::factory()->count(25)->create();

        $response = $this->get(route('admin.handoffs.index'));
        $response->assertOk();

        $payload = $response->viewData('handoffs');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Resource shape — AdminHandoffLockResource per FRD-040 § 5
    // ---------------------------------------------------------------------

    public function test_it_returns_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'name' => 'Admin Reyes',
        ]);
        $this->actingAs($admin, 'admin');

        $renter = Renter::factory()->create([
            'name'  => 'Maria Cruz',
            'phone' => '+639171234567',
        ]);
        $listing = Listing::factory()->verified()->create([
            'title'         => 'Beachfront Condo',
            'barangay'      => 'carmen',
            'contact_phone' => '+639998887777',
            'contact_type'  => ContactType::owner()->value,
        ]);
        $inquiry = Inquiry::factory()->handedOff($admin)->for($renter)->for($listing)->create();

        HandoffLock::create([
            'inquiry_id' => $inquiry->id,
            'listing_id' => $listing->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->get(route('admin.handoffs.index'));
        $response->assertOk();

        $row = $response->viewData('handoffs')['data'][0];

        $this->assertEqualsCanonicalizing(
            ['id', 'created_at', 'created_by_name', 'listing', 'renter'],
            array_keys($row),
        );
        $this->assertEqualsCanonicalizing(
            ['uuid', 'title', 'barangay_label', 'contact_phone'],
            array_keys($row['listing']),
        );
        $this->assertEqualsCanonicalizing(
            ['name', 'phone'],
            array_keys($row['renter']),
        );

        $this->assertSame('Admin Reyes', $row['created_by_name']);
        $this->assertNotNull($row['created_at']);

        $this->assertSame($listing->uuid, $row['listing']['uuid']);
        $this->assertSame('Beachfront Condo', $row['listing']['title']);
        $this->assertNotEmpty($row['listing']['barangay_label']);
        $this->assertSame('+639998887777', $row['listing']['contact_phone']);

        $this->assertSame('Maria Cruz', $row['renter']['name']);
        $this->assertSame('+639171234567', $row['renter']['phone']);
    }

    // ---------------------------------------------------------------------
    // Audit-log carve-out — created_by uses nullOnDelete so lock history
    // survives a hard-deleted admin. Resource collapses null to literal
    // 'Deleted admin' for cosmetic display.
    // ---------------------------------------------------------------------

    public function test_it_handles_deleted_admin_in_created_by_field(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin, 'admin');

        $authoringAdmin = User::factory()->superAdmin()->create();
        $lock = HandoffLock::factory()->forCreatedBy($authoringAdmin)->create();

        $authoringAdmin->forceDelete();

        $lock->refresh();
        $this->assertNull(
            $lock->created_by,
            'nullOnDelete cascade must clear created_by when authoring admin is hard-deleted.',
        );

        $response = $this->get(route('admin.handoffs.index'));
        $response->assertOk();

        $row = $response->viewData('handoffs')['data'][0];
        $this->assertSame('Deleted admin', $row['created_by_name']);
    }
}
