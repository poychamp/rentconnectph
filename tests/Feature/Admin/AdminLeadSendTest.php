<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminLeadSendTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.send', $lead))
            ->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.send', $lead))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Route binding — 404 on unknown uuid (route-model binding short-circuit)
    // ---------------------------------------------------------------------

    public function test_it_404s_for_unknown_lead_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->put('/admin/leads/00000000-0000-0000-0000-000000000000/send')
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // State guards — must surface in named bag `send-{uuid}` per
    // feedback_state_guard_via_validation_exception.md. Distinct messages
    // per source state. Status must NOT change on guard rejection.
    // ---------------------------------------------------------------------

    public function test_it_throws_state_error_in_named_bag_when_lead_is_already_sent(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->sent()->create();

        $response = $this->put(route('admin.leads.send', $lead));

        $response->assertSessionHasErrorsIn(
            'send-' . $lead->uuid,
            ['_state' => 'This lead has already been sent.'],
        );

        $this->assertSame(
            LeadStatus::sent()->value,
            Lead::find($lead->id)->status,
            'Status must remain `sent` when guard rejects re-send attempt.',
        );
    }

    public function test_it_throws_state_error_in_named_bag_when_lead_is_finalized(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->finalized()->create();

        $response = $this->put(route('admin.leads.send', $lead));

        $response->assertSessionHasErrorsIn(
            'send-' . $lead->uuid,
            ['_state' => 'Cannot send a finalized lead.'],
        );

        $this->assertSame(
            LeadStatus::finalized()->value,
            Lead::find($lead->id)->status,
            'Status must remain `finalized` when guard rejects send-from-terminal.',
        );
    }

    public function test_it_throws_state_error_in_named_bag_when_lead_is_lost(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->lost()->create();

        $response = $this->put(route('admin.leads.send', $lead));

        $response->assertSessionHasErrorsIn(
            'send-' . $lead->uuid,
            ['_state' => 'Cannot send a lost lead.'],
        );

        $this->assertSame(
            LeadStatus::lost()->value,
            Lead::find($lead->id)->status,
            'Status must remain `lost` when guard rejects send-from-terminal.',
        );
    }

    // ---------------------------------------------------------------------
    // Happy path — pending → sent
    // ---------------------------------------------------------------------

    public function test_it_flips_status_pending_to_sent_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.send', $lead));

        $this->assertSame(
            LeadStatus::sent()->value,
            Lead::find($lead->id)->status,
        );
    }

    public function test_it_writes_sent_at_timestamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $now = Carbon::create(2026, 5, 5, 14, 30);
        Carbon::setTestNow($now);

        $lead = Lead::factory()->pending()->create();

        $this->put(route('admin.leads.send', $lead));

        $sentAt = Lead::find($lead->id)->sent_at;
        $this->assertNotNull($sentAt, 'sent_at must be populated after PUT.');
        $this->assertTrue(
            $sentAt->equalTo($now),
            'sent_at must equal the Carbon::now() instant captured by the controller.',
        );

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------------
    // Notes overwrite — payload value replaces existing column verbatim.
    // Empty/missing payload normalizes to null and clears the column.
    // Matches `Admin\InquiryController::handoff` + `::reject` convention
    // already established in the codebase (FRD-039 / FRD-043).
    // ---------------------------------------------------------------------

    public function test_it_overwrites_notes_with_payload_value(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create([
            'notes' => 'Original creation notes.',
        ]);

        $this->put(route('admin.leads.send', $lead), [
            'notes' => 'FB Messenger 2pm — Marco confirmed.',
        ]);

        $this->assertSame(
            'FB Messenger 2pm — Marco confirmed.',
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

        $this->put(route('admin.leads.send', $leadA), ['notes' => '']);

        $this->assertNull(
            Lead::find($leadA->id)->notes,
            'Empty-string notes payload must clear the column to null.',
        );

        // Branch B: payload omits the `notes` key entirely.
        $leadB = Lead::factory()->pending()->create(['notes' => 'Original notes B.']);

        $this->put(route('admin.leads.send', $leadB), []);

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

        $response = $this->put(route('admin.leads.send', $lead), [
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertSessionHasErrorsIn(
            'send-' . $lead->uuid,
            ['notes' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $fresh = Lead::find($lead->id);
        $this->assertSame(LeadStatus::pending()->value, $fresh->status, 'Status must remain pending on validation failure.');
        $this->assertNull($fresh->sent_at, 'sent_at must NOT be written on validation failure.');
    }

    // ---------------------------------------------------------------------
    // Flash + redirect — back() with flat-key success flash per CLAUDE.md.
    // ---------------------------------------------------------------------

    public function test_it_redirects_back_with_success_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $lead = Lead::factory()->pending()->create();

        $response = $this->put(route('admin.leads.send', $lead));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lead marked as sent.');
    }
}
