<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactType;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminInquiryFilteredViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $response = $this->get(route('admin.filtered-inquiries.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.filtered-inquiries.index'))->assertForbidden();
    }

    public function test_it_loads_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));

        $response->assertOk();
        $response->assertViewIs('admin.inquiries.filtered-index');

        $payload = $response->viewData('inquiries');
        $this->assertCount(1, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Slice scope
    // ---------------------------------------------------------------------

    public function test_it_filters_status_new_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->create();
        Inquiry::factory()->dead()->create();
        Inquiry::factory()->dead()->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'Filtered slice must include only status=new rows');
        $this->assertSame(InquiryStatus::new()->value, $rows[0]['status']);
    }

    // ---------------------------------------------------------------------
    // Sort
    // ---------------------------------------------------------------------

    public function test_it_orders_by_created_at_asc_then_id_asc(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $base = Carbon::create(2026, 1, 1, 12, 0, 0);

        Carbon::setTestNow($base);
        $oldest = Inquiry::factory()->create();

        Carbon::setTestNow($base->copy()->addDay());
        $middle = Inquiry::factory()->create();

        Carbon::setTestNow($base->copy()->addDays(2));
        $newest = Inquiry::factory()->create();

        Carbon::setTestNow();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertSame(
            [$oldest->uuid, $middle->uuid, $newest->uuid],
            $uuids,
            'Filtered tab must order by created_at ASC (oldest first) — calls team queue priority',
        );
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    public function test_it_paginates_at_10_per_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->count(25)->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $payload = $response->viewData('inquiries');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Resource shape — includes call-context fields (contact_phone,
    // contact_type_label, verification_notes) so calls team can qualify
    // and hand over contact directly from the queue.
    // ---------------------------------------------------------------------

    public function test_it_returns_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter = Renter::factory()->create([
            'name'         => 'Maria Cruz',
            'phone'        => '+639171234567',
            'is_qualified' => false,
        ]);
        $listing = Listing::factory()->verified()->create([
            'title'              => 'Beachfront Condo',
            'barangay'           => 'carmen',
            'contact_phone'      => '+639998887777',
            'contact_type'       => ContactType::owner()->value,
            'verification_notes' => 'Met owner at viewing, key handoff smooth.',
        ]);

        Inquiry::factory()->for($renter)->for($listing)->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $row = $response->viewData('inquiries')['data'][0];

        $this->assertEqualsCanonicalizing(
            ['uuid', 'status', 'status_label', 'submitted_at', 'renter', 'listing'],
            array_keys($row),
        );
        $this->assertEqualsCanonicalizing(
            ['name', 'phone', 'is_qualified', 'prior_rejected_count', 'notes'],
            array_keys($row['renter']),
        );
        $this->assertEqualsCanonicalizing(
            ['uuid', 'title', 'barangay_label', 'contact_phone', 'contact_type_label', 'verification_notes'],
            array_keys($row['listing']),
        );

        $this->assertSame(InquiryStatus::new()->value, $row['status']);
        $this->assertSame('New', $row['status_label']);

        $this->assertSame('Maria Cruz', $row['renter']['name']);
        $this->assertSame('+639171234567', $row['renter']['phone']);
        $this->assertFalse($row['renter']['is_qualified']);

        $this->assertSame('Beachfront Condo', $row['listing']['title']);
        $this->assertNotEmpty($row['listing']['barangay_label']);
        $this->assertSame('+639998887777', $row['listing']['contact_phone']);
        $this->assertSame('Owner', $row['listing']['contact_type_label']);
        $this->assertSame('Met owner at viewing, key handoff smooth.', $row['listing']['verification_notes']);

        $this->assertNotNull($row['submitted_at']);
    }

    // ---------------------------------------------------------------------
    // Counts
    // ---------------------------------------------------------------------

    public function test_it_returns_correct_filtered_and_all_counts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->count(3)->create();
        Inquiry::factory()->dead()->count(2)->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $this->assertSame(
            ['filtered' => 3, 'all' => 5],
            $response->viewData('counts'),
        );
    }

    // ---------------------------------------------------------------------
    // N+1 prevention
    // ---------------------------------------------------------------------

    public function test_it_eager_loads_renter_and_listing_relations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->count(5)->create();

        DB::enableQueryLog();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Tolerant threshold — generous for auth + permission joins + paginate
        // count + paginate data + 2 eager loads + 2 count queries + session
        // bookkeeping. 5 inquiries with eager-loaded renter + listing should
        // yield ~8-12 queries; 20 catches genuine N+1 regressions.
        $this->assertLessThanOrEqual(
            20,
            $queryCount,
            "Filtered inquiries index should eager-load renter + listing — query count was {$queryCount}, suggests N+1 regression.",
        );
    }
}
