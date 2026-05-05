<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminLeadLoseTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.lose', $lead))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.lose', $lead))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Route binding — 404 on unknown uuid (route-model binding short-circuit)
    // ---------------------------------------------------------------------

    public function test_it_404s_for_unknown_lead_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->put('/admin/leads/00000000-0000-0000-0000-000000000000/lose')
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // State guards — must surface in named bag `lose-{uuid}` per
    // feedback_state_guard_via_validation_exception.md. Two illegal source
    // states (vs PRD-047 /send's three): `finalized` + already-`lost`.
    // `pending` and `sent` are happy-path source states (cases 6 + 7).
    // Status must NOT change on guard rejection.
    // ---------------------------------------------------------------------

    public function test_it_throws_state_error_in_named_bag_when_lead_is_already_lost(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->lost()->create();

        $response = $this->put(route('admin.leads.lose', $lead));

        $response->assertSessionHasErrorsIn(
            'lose-' . $lead->uuid,
            ['_state' => 'This lead has already been lost.'],
        );

        $this->assertSame(
            LeadStatus::lost()->value,
            Lead::find($lead->id)->status,
            'Status must remain `lost` when guard rejects re-lose attempt.',
        );
    }

    public function test_it_throws_state_error_in_named_bag_when_lead_is_finalized(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->finalized()->create();

        $response = $this->put(route('admin.leads.lose', $lead));

        $response->assertSessionHasErrorsIn(
            'lose-' . $lead->uuid,
            ['_state' => 'Cannot mark a finalized lead as lost.'],
        );

        $this->assertSame(
            LeadStatus::finalized()->value,
            Lead::find($lead->id)->status,
            'Status must remain `finalized` when guard rejects lose-from-terminal.',
        );
    }

    // ---------------------------------------------------------------------
    // Happy path — both source states must flip to `lost`. Two cases (vs
    // PRD-047 /send's one) since /lose accepts pending OR sent as source.
    // ---------------------------------------------------------------------

    public function test_it_flips_status_pending_to_lost_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.lose', $lead));

        $this->assertSame(
            LeadStatus::lost()->value,
            Lead::find($lead->id)->status,
        );
    }

    public function test_it_flips_status_sent_to_lost_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $this->put(route('admin.leads.lose', $lead));

        $this->assertSame(
            LeadStatus::lost()->value,
            Lead::find($lead->id)->status,
        );
    }

    public function test_it_writes_lost_at_timestamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $now = Carbon::create(2026, 5, 5, 14, 30);
        Carbon::setTestNow($now);

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.lose', $lead));

        $lostAt = Lead::find($lead->id)->lost_at;
        $this->assertNotNull($lostAt, 'lost_at must be populated after PUT.');
        $this->assertTrue(
            $lostAt->equalTo($now),
            'lost_at must equal the Carbon::now() instant captured by the controller.',
        );

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------------
    // Notes overwrite — payload value replaces existing column verbatim.
    // Empty/missing payload normalizes to null and clears the column.
    // Locked from PRD-047 (post-pivot) — matches `Admin\LeadController::send`
    // + `Admin\InquiryController::handoff` + `::reject` exactly.
    // ---------------------------------------------------------------------

    public function test_it_overwrites_notes_with_payload_value(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create([
            'notes' => 'Renter agreed to broker fee terms.',
        ]);

        $this->put(route('admin.leads.lose', $lead), [
            'notes' => 'Renter ghosted Marco after 3 attempts.',
        ]);

        $this->assertSame(
            'Renter ghosted Marco after 3 attempts.',
            Lead::find($lead->id)->notes,
            'Notes column must be overwritten with payload value verbatim.',
        );
    }

    public function test_it_clears_notes_to_null_when_payload_notes_is_empty(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Branch A: payload `notes` is the empty string.
        $leadA = Lead::factory()->pending()->create(['notes' => 'Original notes A.']);

        $this->put(route('admin.leads.lose', $leadA), ['notes' => '']);

        $this->assertNull(
            Lead::find($leadA->id)->notes,
            'Empty-string notes payload must clear the column to null.',
        );

        // Branch B: payload omits the `notes` key entirely.
        $leadB = Lead::factory()->pending()->create(['notes' => 'Original notes B.']);

        $this->put(route('admin.leads.lose', $leadB), []);

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

        $lead = Lead::factory()->pending()->create();

        $response = $this->put(route('admin.leads.lose', $lead), [
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertSessionHasErrorsIn(
            'lose-' . $lead->uuid,
            ['notes' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::pending()->value, $fresh->status, 'Status must remain pending on validation failure.');
        $this->assertNull($fresh->lost_at, 'lost_at must NOT be written on validation failure.');
    }

    // ---------------------------------------------------------------------
    // Flash + redirect — back() with flat-key success flash per CLAUDE.md.
    // ---------------------------------------------------------------------

    public function test_it_redirects_back_with_success_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create();

        $response = $this->put(route('admin.leads.lose', $lead));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lead marked as lost.');
    }

    // ---------------------------------------------------------------------
    // Invariant pin — `sent_at` must be PRESERVED across a `sent → lost`
    // flip. The lose update payload must only touch status/lost_at/notes;
    // a future refactor that adds `sent_at => null` (or any sent_at write)
    // to the lose update will fail this test loudly.
    // ---------------------------------------------------------------------

    public function test_it_preserves_sent_at_when_losing_a_sent_lead(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $beforeAt = $lead->sent_at;
        $this->assertNotNull(
            $beforeAt,
            'LeadFactory::sent() must populate sent_at as a setup precondition.',
        );

        $this->put(route('admin.leads.lose', $lead));

        $afterAt = Lead::find($lead->id)->sent_at;
        $this->assertNotNull($afterAt, 'sent_at must NOT be cleared on sent→lost flip.');
        $this->assertTrue(
            $afterAt->equalTo($beforeAt),
            'sent_at must equal its pre-flip value (preservation invariant).',
        );
    }
}
