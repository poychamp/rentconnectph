<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactType;
use App\Enums\LeadStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminLeadViewTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $this->get(route('admin.leads.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $this->get(route('admin.leads.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_it_loads_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->create();

        $response = $this->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertViewIs('admin.leads.index');

        $this->assertCount(1, $response->viewData('leads')['data']);
    }

    // ---------------------------------------------------------------------
    // Sort — created_at DESC, id DESC tiebreaker (newest first per FRD-046
    // § 4). Shuffled creation order via Carbon::setTestNow defeats the
    // id-tiebreaker pass-by-coincidence.
    // ---------------------------------------------------------------------

    public function test_it_orders_by_created_at_desc_then_id_desc(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $base = Carbon::create(2026, 5, 1, 12, 0, 0);

        Carbon::setTestNow($base->copy()->addDay());
        $middle = Lead::factory()->create();

        Carbon::setTestNow($base->copy()->addDays(2));
        $newest = Lead::factory()->create();

        Carbon::setTestNow($base);
        $oldest = Lead::factory()->create();

        Carbon::setTestNow();

        $response = $this->get(route('admin.leads.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('leads')['data']);

        $this->assertSame(
            [$newest->uuid, $middle->uuid, $oldest->uuid],
            $uuids,
            'Leads index must order by created_at DESC — newest pipeline-entries first.',
        );
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    public function test_it_paginates_at_10_per_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->count(25)->create();

        $response = $this->get(route('admin.leads.index'));
        $response->assertOk();

        $payload = $response->viewData('leads');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertCount(10, $payload['data']);
    }

    // ---------------------------------------------------------------------
    // Resource shape — AdminLeadResource per FRD-046 § 5. Pin all 14
    // top-level keys + nested inquiry/renter/listing/listing_detail_url
    // shapes + canonical per-field values.
    // ---------------------------------------------------------------------

    public function test_it_returns_resource_shape(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'name' => 'Admin Reyes',
        ]);
        $this->actingAs($admin, 'admin');

        $renter = Renter::factory()->create([
            'name'         => 'Maria Cruz',
            'phone'        => '+639171234567',
            'is_qualified' => true,
            'notes'        => 'Steady employment, low risk.',
        ]);

        $listing = Listing::factory()->verified()->create([
            'title'              => 'Beachfront Condo',
            'barangay'           => 'carmen',
            'price_monthly'      => 18000,
            'contact_phone'      => '+639998887777',
            'contact_type'       => ContactType::owner()->value,
            'verification_notes' => 'Owner answered on first call.',
        ]);

        $inquiry = Inquiry::factory()->handedOff($admin)->for($renter)->for($listing)->create([
            'notes' => 'Move-in target Jun 2026.',
        ]);

        $lead = Lead::create([
            'inquiry_id' => $inquiry->id,
            'created_by' => $admin->id,
            'status'     => LeadStatus::pending()->value,
            'notes'      => 'Renter agreed to broker fee terms.',
        ]);

        $response = $this->get(route('admin.leads.index'));
        $response->assertOk();

        $row = $response->viewData('leads')['data'][0];

        // Top-level keys.
        $this->assertEqualsCanonicalizing(
            [
                'uuid', 'status', 'status_label', 'created_at', 'created_by_name',
                'sent_at', 'finalized_at', 'lost_at', 'notes', 'monthly_rent',
                'inquiry', 'renter', 'listing', 'listing_detail_url',
            ],
            array_keys($row),
        );

        // Nested key shapes.
        $this->assertEqualsCanonicalizing(
            ['uuid', 'notes'],
            array_keys($row['inquiry']),
        );
        $this->assertEqualsCanonicalizing(
            ['name', 'phone', 'is_qualified', 'notes'],
            array_keys($row['renter']),
        );
        $this->assertEqualsCanonicalizing(
            ['uuid', 'title', 'barangay_label', 'contact_phone', 'contact_type_label', 'verification_notes'],
            array_keys($row['listing']),
        );

        // Canonical per-field values.
        $this->assertSame($lead->uuid, $row['uuid']);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('Pending', $row['status_label']);
        $this->assertNotNull($row['created_at']);
        $this->assertSame('Admin Reyes', $row['created_by_name']);
        $this->assertNull($row['sent_at']);
        $this->assertNull($row['finalized_at']);
        $this->assertNull($row['lost_at']);
        $this->assertSame('Renter agreed to broker fee terms.', $row['notes']);
        $this->assertSame(18000, $row['monthly_rent']);

        $this->assertSame($inquiry->uuid, $row['inquiry']['uuid']);
        $this->assertSame('Move-in target Jun 2026.', $row['inquiry']['notes']);

        $this->assertSame('Maria Cruz', $row['renter']['name']);
        $this->assertSame('+639171234567', $row['renter']['phone']);
        $this->assertTrue($row['renter']['is_qualified']);
        $this->assertSame('Steady employment, low risk.', $row['renter']['notes']);

        $this->assertSame($listing->uuid, $row['listing']['uuid']);
        $this->assertSame('Beachfront Condo', $row['listing']['title']);
        $this->assertNotEmpty($row['listing']['barangay_label']);
        $this->assertSame('+639998887777', $row['listing']['contact_phone']);
        $this->assertSame('Owner', $row['listing']['contact_type_label']);
        $this->assertSame('Owner answered on first call.', $row['listing']['verification_notes']);

        $this->assertSame('/listings/' . $listing->uuid, $row['listing_detail_url']);
    }

    // ---------------------------------------------------------------------
    // Status filter — one case per LeadStatus value. Seeds 1 lead per
    // status (4 total) and asserts ?status=X returns only the matching row.
    // ---------------------------------------------------------------------

    public function test_it_filters_by_status_pending(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'pending']));
        $response->assertOk();

        $rows = $response->viewData('leads')['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows[0]['status']);
    }

    public function test_it_filters_by_status_sent(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'sent']));
        $response->assertOk();

        $rows = $response->viewData('leads')['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('sent', $rows[0]['status']);
    }

    public function test_it_filters_by_status_finalized(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'finalized']));
        $response->assertOk();

        $rows = $response->viewData('leads')['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('finalized', $rows[0]['status']);
    }

    public function test_it_filters_by_status_lost(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'lost']));
        $response->assertOk();

        $rows = $response->viewData('leads')['data'];
        $this->assertCount(1, $rows);
        $this->assertSame('lost', $rows[0]['status']);
    }

    // ---------------------------------------------------------------------
    // Silent fall-through on unknown ?status values. Validates the
    // controller's in_array(LeadStatus::toValues(), true) allowlist guard.
    // Pin per FRD-046 § 4 + spec-leads.md: NOT 422, NOT redirect — full
    // list returned.
    // ---------------------------------------------------------------------

    public function test_it_silently_falls_through_unknown_status_value(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'garbage_value']));
        $response->assertOk();

        $this->assertCount(4, $response->viewData('leads')['data']);
    }

    // ---------------------------------------------------------------------
    // Audit-log carve-out — leads.created_by uses nullOnDelete (per
    // FRD-045) so lead history survives a hard-deleted authoring admin.
    // AdminLeadResource returns null (NOT 'Deleted admin' literal — that's
    // AdminHandoffLockResource's pattern). FRD-046 § 5 decision: frontend
    // formats fallback at row level, resource stays neutral.
    // ---------------------------------------------------------------------

    public function test_it_handles_deleted_admin_in_created_by_field(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin, 'admin');

        $authoringAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['created_by' => $authoringAdmin->id]);

        $authoringAdmin->forceDelete();

        $lead->refresh();
        $this->assertNull(
            $lead->created_by,
            'nullOnDelete cascade must clear leads.created_by when authoring admin is hard-deleted.',
        );

        $response = $this->get(route('admin.leads.index'));
        $response->assertOk();

        $row = $response->viewData('leads')['data'][0];
        $this->assertNull(
            $row['created_by_name'],
            'AdminLeadResource::created_by_name must return null (NOT a literal) so the frontend formats the fallback at row level.',
        );
    }

    // ---------------------------------------------------------------------
    // State-companion timestamp serialization. Pin per-state truthy/null
    // shape on sent_at / finalized_at / lost_at. Mirrors LeadFactory's
    // state methods: ::sent() sets sent_at; ::finalized() sets BOTH
    // sent_at AND finalized_at; ::lost() sets ONLY lost_at.
    // ---------------------------------------------------------------------

    public function test_it_serializes_state_companion_timestamps_per_state(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->create();
        Lead::factory()->sent()->create();
        Lead::factory()->finalized()->create();
        Lead::factory()->lost()->create();

        $response = $this->get(route('admin.leads.index'));
        $response->assertOk();

        $rows = $response->viewData('leads')['data'];

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[$row['status']] = $row;
        }

        $this->assertNull($byStatus['pending']['sent_at']);
        $this->assertNull($byStatus['pending']['finalized_at']);
        $this->assertNull($byStatus['pending']['lost_at']);

        $this->assertNotNull($byStatus['sent']['sent_at']);
        $this->assertNull($byStatus['sent']['finalized_at']);
        $this->assertNull($byStatus['sent']['lost_at']);

        $this->assertNotNull($byStatus['finalized']['sent_at']);
        $this->assertNotNull($byStatus['finalized']['finalized_at']);
        $this->assertNull($byStatus['finalized']['lost_at']);

        $this->assertNull($byStatus['lost']['sent_at']);
        $this->assertNull($byStatus['lost']['finalized_at']);
        $this->assertNotNull($byStatus['lost']['lost_at']);
    }

    // ---------------------------------------------------------------------
    // Filter-preserving pagination. Defends against forgetting
    // ->appends($request->only(['status'])) on the controller's paginate
    // call. Without it, page-2 nav loses the filter.
    // ---------------------------------------------------------------------

    public function test_it_preserves_status_filter_in_pagination(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        Lead::factory()->pending()->count(25)->create();
        Lead::factory()->sent()->count(5)->create();
        Lead::factory()->finalized()->count(5)->create();
        Lead::factory()->lost()->count(5)->create();

        $response = $this->get(route('admin.leads.index', ['status' => 'pending']));
        $response->assertOk();

        $payload = $response->viewData('leads');

        $this->assertSame(25, $payload['meta']['total']);
        $this->assertCount(10, $payload['data']);

        $nextUrl = $payload['links']['next'] ?? null;
        $this->assertNotNull(
            $nextUrl,
            'next-page link must exist when total > per_page.',
        );
        $this->assertStringContainsString(
            'status=pending',
            $nextUrl,
            'paginate()->appends() must carry the status filter into pagination links.',
        );
    }
}
