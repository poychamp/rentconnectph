<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminLeadFinalizeTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $lead = Lead::factory()->sent()->create();

        $this->put(route('admin.leads.finalize', $lead))
            ->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $lead = Lead::factory()->sent()->create();

        $this->put(route('admin.leads.finalize', $lead))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Route binding — 404 on unknown uuid (route-model binding short-circuit)
    // ---------------------------------------------------------------------

    public function test_it_404s_for_unknown_lead_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->put('/admin/leads/00000000-0000-0000-0000-000000000000/finalize')
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // State guards — must surface in named bag `finalize-{uuid}` per
    // feedback_state_guard_via_validation_exception.md. Three illegal
    // source states (mirrors PRD-047 /send): pending + finalized + lost.
    // `sent` is the only legal source (case 7).
    // Status must NOT change on guard rejection. For the already-finalized
    // case, finalized_at must NOT be re-bumped on guard rejection
    // (snapshot equality — defensive extra assertion).
    // ---------------------------------------------------------------------

    public function test_it_throws_state_error_in_named_bag_when_lead_is_pending(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create();

        $response = $this->put(route('admin.leads.finalize', $lead));

        $response->assertSessionHasErrorsIn(
            'finalize-' . $lead->uuid,
            ['_state' => 'Cannot finalize a pending lead.'],
        );

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::pending()->value, $fresh->status, 'Status must remain `pending` when guard rejects finalize-from-pending.');
        $this->assertNull($fresh->finalized_at, 'finalized_at must NOT be written on guard rejection.');
    }

    public function test_it_throws_state_error_in_named_bag_when_lead_is_already_finalized(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead     = Lead::factory()->finalized()->create();
        $beforeAt = $lead->finalized_at;
        $this->assertNotNull($beforeAt, 'LeadFactory::finalized() must populate finalized_at as a setup precondition.');

        $response = $this->put(route('admin.leads.finalize', $lead));

        $response->assertSessionHasErrorsIn(
            'finalize-' . $lead->uuid,
            ['_state' => 'This lead has already been finalized.'],
        );

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::finalized()->value, $fresh->status, 'Status must remain `finalized` when guard rejects re-finalize attempt.');
        $this->assertTrue(
            $fresh->finalized_at->equalTo($beforeAt),
            'finalized_at must NOT be re-bumped on guard rejection (snapshot equality).',
        );
    }

    public function test_it_throws_state_error_in_named_bag_when_lead_is_lost(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->lost()->create();

        $response = $this->put(route('admin.leads.finalize', $lead));

        $response->assertSessionHasErrorsIn(
            'finalize-' . $lead->uuid,
            ['_state' => 'Cannot finalize a lost lead.'],
        );

        $this->assertSame(
            LeadStatus::lost()->value,
            Lead::find($lead->id)->status,
            'Status must remain `lost` when guard rejects finalize-from-terminal.',
        );
    }

    // ---------------------------------------------------------------------
    // Happy path — single source state (`sent` → `finalized`).
    // Mirrors PRD-047 /send's single-source-state shape.
    // ---------------------------------------------------------------------

    public function test_it_flips_status_sent_to_finalized_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $this->put(route('admin.leads.finalize', $lead));

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::finalized()->value, $fresh->status);
        $this->assertNotNull($fresh->finalized_at, 'finalized_at must be populated after happy-path PUT.');
    }

    public function test_it_writes_finalized_at_timestamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $now = Carbon::create(2026, 5, 5, 14, 30);
        Carbon::setTestNow($now);

        $lead = Lead::factory()->sent()->create();

        $this->put(route('admin.leads.finalize', $lead));

        $finalizedAt = Lead::find($lead->id)->finalized_at;
        $this->assertNotNull($finalizedAt, 'finalized_at must be populated after PUT.');
        $this->assertTrue(
            $finalizedAt->equalTo($now),
            'finalized_at must equal the Carbon::now() instant captured by the controller.',
        );

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------------
    // Notes overwrite — payload value replaces existing column verbatim.
    // Empty/missing payload normalizes to null and clears the column.
    // Locked from PRD-047 (post-pivot) — matches `Admin\LeadController::send`
    // + `::lose` + `Admin\InquiryController::handoff` + `::reject` exactly.
    // ---------------------------------------------------------------------

    public function test_it_overwrites_notes_with_payload_value(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create([
            'notes' => 'Sent context — Marco confirmed receipt.',
        ]);

        $this->put(route('admin.leads.finalize', $lead), [
            'notes' => 'Lease signed Tue 4pm — final rent ₱22,000, 12-month term.',
        ]);

        $this->assertSame(
            'Lease signed Tue 4pm — final rent ₱22,000, 12-month term.',
            Lead::find($lead->id)->notes,
            'Notes column must be overwritten with payload value verbatim.',
        );
    }

    public function test_it_clears_notes_to_null_when_payload_notes_is_empty(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Branch A: payload `notes` is the empty string.
        $leadA = Lead::factory()->sent()->create(['notes' => 'Original notes A.']);

        $this->put(route('admin.leads.finalize', $leadA), ['notes' => '']);

        $this->assertNull(
            Lead::find($leadA->id)->notes,
            'Empty-string notes payload must clear the column to null.',
        );

        // Branch B: payload omits the `notes` key entirely.
        $leadB = Lead::factory()->sent()->create(['notes' => 'Original notes B.']);

        $this->put(route('admin.leads.finalize', $leadB), []);

        $this->assertNull(
            Lead::find($leadB->id)->notes,
            'Absent notes key must clear the column to null.',
        );
    }

    // ---------------------------------------------------------------------
    // Validation — notes max length surfaces in the named bag; state +
    // timestamp must NOT advance when validation rejects.
    // ---------------------------------------------------------------------

    public function test_it_validates_notes_max_length_into_named_bag(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $response = $this->put(route('admin.leads.finalize', $lead), [
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertSessionHasErrorsIn(
            'finalize-' . $lead->uuid,
            ['notes' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::sent()->value, $fresh->status, 'Status must remain sent on validation failure.');
        $this->assertNull($fresh->finalized_at, 'finalized_at must NOT be written on validation failure.');
    }

    // ---------------------------------------------------------------------
    // Flash + redirect — back() with flat-key success flash per CLAUDE.md.
    // ---------------------------------------------------------------------

    public function test_it_redirects_back_with_success_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $response = $this->put(route('admin.leads.finalize', $lead));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lead marked as finalized.');
    }

    // ---------------------------------------------------------------------
    // Invariant pin — `sent_at` must be PRESERVED across a `sent → finalized`
    // flip. The finalize update payload must only touch status/finalized_at/notes;
    // a future refactor that adds `sent_at => null` (or any sent_at write)
    // to the finalize update will fail this test loudly. Second explicit
    // pin in the codebase for state-companion timestamp preservation
    // (mirrors AdminLeadLoseTest::test_it_preserves_sent_at_when_losing_a_sent_lead).
    // ---------------------------------------------------------------------

    public function test_it_preserves_sent_at_when_finalizing_a_sent_lead(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $beforeAt = $lead->sent_at;
        $this->assertNotNull(
            $beforeAt,
            'LeadFactory::sent() must populate sent_at as a setup precondition.',
        );

        $this->put(route('admin.leads.finalize', $lead));

        $afterAt = Lead::find($lead->id)->sent_at;
        $this->assertNotNull($afterAt, 'sent_at must NOT be cleared on sent→finalized flip.');
        $this->assertTrue(
            $afterAt->equalTo($beforeAt),
            'sent_at must equal its pre-flip value (preservation invariant).',
        );
    }
}
