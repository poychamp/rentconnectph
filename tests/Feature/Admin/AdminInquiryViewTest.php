<?php

namespace Tests\Feature\Admin;

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

class AdminInquiryViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $response = $this->get(route('admin.inquiries.index'));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.inquiries.index'))->assertForbidden();
    }

    public function test_it_loads_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->create();

        $response = $this->get(route('admin.inquiries.index'));

        $response->assertOk();
        $response->assertViewIs('admin.inquiries.index');

        $payload = $response->viewData('inquiries');
        $this->assertCount(1, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Slice — All tab shows EVERY status (no whereDoesntHave('activeHandoff'))
    // ---------------------------------------------------------------------

    public function test_it_returns_inquiries_across_all_statuses(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Inquiry::factory()->create();              // status=new
        Inquiry::factory()->handedOff()->create(); // status=handed_off
        Inquiry::factory()->rejected()->create();  // status=rejected

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(3, $rows, 'All tab must include every status (new + handed_off + rejected)');

        $statuses = array_column($rows, 'status');
        sort($statuses);
        $this->assertSame(['handed_off', 'new', 'rejected'], $statuses);
    }

    // ---------------------------------------------------------------------
    // Sort — newest first (opposite of Filtered tab's oldest-first queue)
    // ---------------------------------------------------------------------

    public function test_it_orders_by_updated_at_desc_then_id_desc(): void
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

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertSame(
            [$newest->uuid, $middle->uuid, $oldest->uuid],
            $uuids,
            'All tab must order by updated_at DESC (most-recently-touched first) — reference lookup priority',
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

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $payload = $response->viewData('inquiries');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Search — renter name substring (case-insensitive LIKE)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_name_substring(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $maria = Renter::factory()->create(['name' => 'Maria Cruz']);
        $pedro = Renter::factory()->create(['name' => 'Pedro Cruz']);
        $juan  = Renter::factory()->create(['name' => 'Juan Reyes']);

        Inquiry::factory()->for($maria)->create();
        Inquiry::factory()->for($pedro)->create();
        Inquiry::factory()->for($juan)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => 'cruz']));
        $response->assertOk();

        $names = array_map(fn ($r) => $r['renter']['name'], $response->viewData('inquiries')['data']);
        sort($names);

        $this->assertSame(['Maria Cruz', 'Pedro Cruz'], $names, '?q=cruz must match both Cruz renters and exclude Reyes (case-insensitive LIKE on renter.name)');
    }

    // ---------------------------------------------------------------------
    // Search — renter phone in local format (PhMobile::normalize round-trip)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_phone_in_local_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $target = Renter::factory()->create(['phone' => '+639171234567']);
        $other  = Renter::factory()->create(['phone' => '+639998887777']);

        Inquiry::factory()->for($target)->create();
        Inquiry::factory()->for($other)->create();

        // Local form 09171234567 normalizes to +639171234567 — controller's
        // PhMobile::normalize equality clause must catch the E.164-stored row.
        $response = $this->get(route('admin.inquiries.index', ['q' => '09171234567']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'Local-format phone query must match E.164-stored renter via PhMobile::normalize round-trip');
        $this->assertSame('+639171234567', $rows[0]['renter']['phone']);
    }

    // ---------------------------------------------------------------------
    // Search — renter phone in E.164 format (raw LIKE clause)
    // ---------------------------------------------------------------------

    public function test_it_filters_by_renter_phone_in_e164_format(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $target = Renter::factory()->create(['phone' => '+639171234567']);
        $other  = Renter::factory()->create(['phone' => '+639998887777']);

        Inquiry::factory()->for($target)->create();
        Inquiry::factory()->for($other)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => '+639171234567']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, 'E.164 phone query must match via raw LIKE clause');
        $this->assertSame('+639171234567', $rows[0]['renter']['phone']);
    }

    // ---------------------------------------------------------------------
    // Search — listing title substring
    // ---------------------------------------------------------------------

    public function test_it_filters_by_listing_title_substring(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $beach = Listing::factory()->verified()->create(['title' => 'Beachfront Condo']);
        $cabin = Listing::factory()->verified()->create(['title' => 'Mountain Cabin']);

        Inquiry::factory()->for($beach)->create();
        Inquiry::factory()->for($cabin)->create();

        $response = $this->get(route('admin.inquiries.index', ['q' => 'beach']));
        $response->assertOk();

        $rows = $response->viewData('inquiries')['data'];
        $this->assertCount(1, $rows, '?q=beach must match Beachfront and exclude Mountain (case-insensitive LIKE on listing.title)');
        $this->assertSame('Beachfront Condo', $rows[0]['listing']['title']);
    }

    // ---------------------------------------------------------------------
    // Resource shape — extended keys (notes, handed_off_at, rejected_at,
    // handed_off_by_name, rejected_by_name) + ?->name graceful nil
    // ---------------------------------------------------------------------

    public function test_it_returns_extended_resource_shape(): void
    {
        $auth  = User::factory()->superAdmin()->create();
        $actor = User::factory()->superAdmin()->create(['name' => 'Mae Handover']);
        $this->actingAs($auth, 'admin');

        $renter = Renter::factory()->create([
            'name'  => 'Maria Cruz',
            'phone' => '+639171234567',
        ]);
        $listing = Listing::factory()->verified()->create([
            'title'    => 'Beachfront Condo',
            'barangay' => 'carmen',
        ]);

        Inquiry::factory()
            ->for($renter)
            ->for($listing)
            ->handedOff($actor)
            ->create([
                'notes' => 'Owner OK with Aug 1 move-in.',
            ]);

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $row = $response->viewData('inquiries')['data'][0];

        $this->assertEqualsCanonicalizing(
            ['uuid', 'status', 'status_label', 'submitted_at', 'notes', 'handed_off_at', 'rejected_at', 'handed_off_by_name', 'rejected_by_name', 'lead', 'renter', 'listing'],
            array_keys($row),
        );

        $this->assertSame(InquiryStatus::handedOff()->value, $row['status']);
        $this->assertSame('Handed Off', $row['status_label']);
        $this->assertSame('Owner OK with Aug 1 move-in.', $row['notes']);
        $this->assertNotNull($row['handed_off_at']);
        $this->assertSame('Mae Handover', $row['handed_off_by_name']);
        $this->assertNull($row['rejected_at']);
        $this->assertNull($row['rejected_by_name']);

        // ?->name graceful nil — after actor is hard-deleted, nullOnDelete
        // cascades the FK to null, and the resource's `?->name` returns null
        // without throwing on a null relation.
        $actor->forceDelete();

        $response2 = $this->get(route('admin.inquiries.index'));
        $row2 = $response2->viewData('inquiries')['data'][0];

        $this->assertNull(
            $row2['handed_off_by_name'],
            'handed_off_by_name must null-out gracefully when admin is hard-deleted (nullOnDelete cascade + ?->name)',
        );
    }

    // ---------------------------------------------------------------------
    // N+1 prevention — eager-load chain includes both actor relations
    // ---------------------------------------------------------------------

    public function test_it_eager_loads_actor_relations(): void
    {
        $auth = User::factory()->superAdmin()->create();
        $this->actingAs($auth, 'admin');

        // 5 handed-off inquiries — each gets its own admin via factory default,
        // so the handedOffBy relation has 5 distinct rows to resolve.
        for ($i = 0; $i < 5; $i++) {
            Inquiry::factory()->handedOff()->create();
        }

        DB::enableQueryLog();

        $response = $this->get(route('admin.inquiries.index'));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Tolerant threshold matches AdminInquiryFilteredViewTest's N+1 budget.
        // 5 inquiries with eager-loaded renter + listing + handedOffBy +
        // rejectedBy should yield ~10-14 queries; 20 catches genuine N+1.
        $this->assertLessThanOrEqual(
            20,
            $queryCount,
            "All-inquiries index should eager-load renter, listing, handedOffBy, rejectedBy — query count was {$queryCount}, suggests N+1 regression.",
        );
    }
}
