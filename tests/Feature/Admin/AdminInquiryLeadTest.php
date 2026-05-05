<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminInquiryLeadTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $inquiry = Inquiry::factory()->handedOff()->create();

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $this->post(route('admin.inquiries.lead.store', $inquiry))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_it_creates_a_lead_in_pending_status_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $this->post(route('admin.inquiries.lead.store', $inquiry));

        $this->assertSame(1, Lead::count());

        $lead = Lead::first();
        $this->assertSame($inquiry->id, $lead->inquiry_id);
        $this->assertSame(LeadStatus::pending()->value, $lead->status);
    }

    public function test_it_writes_created_by_to_acting_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $this->post(route('admin.inquiries.lead.store', $inquiry));

        $this->assertSame($admin->id, Lead::first()->created_by);
    }

    public function test_it_redirects_back_with_success_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lead created — ready to send to broker.');
    }

    // ---------------------------------------------------------------------
    // State guards
    // ---------------------------------------------------------------------

    public function test_it_rejects_with_named_bag_state_error_when_inquiry_status_is_new(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Default factory state is status='new' — the public-inquiry shape
        // before any admin action has been taken.
        $inquiry = Inquiry::factory()->create();
        $this->assertSame(InquiryStatus::new()->value, $inquiry->status);

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry));

        $response->assertSessionHasErrorsIn('lead-' . $inquiry->uuid, [
            '_state' => 'Only handed-off inquiries can be marked as leads.',
        ]);
        $this->assertSame(0, Lead::count());
    }

    public function test_it_rejects_with_named_bag_state_error_when_inquiry_status_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->rejected()->create();

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry));

        $response->assertSessionHasErrorsIn('lead-' . $inquiry->uuid, [
            '_state' => 'Only handed-off inquiries can be marked as leads.',
        ]);
        $this->assertSame(0, Lead::count());
    }

    public function test_it_rejects_with_named_bag_state_error_when_a_lead_already_exists_for_this_inquiry(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();
        Lead::factory()->for($inquiry)->create();

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry));

        $response->assertSessionHasErrorsIn('lead-' . $inquiry->uuid, [
            '_state' => 'A lead already exists for this inquiry.',
        ]);
        $this->assertSame(1, Lead::count(), 'Existing lead must be preserved; no new lead created on duplicate POST.');
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    public function test_it_persists_notes_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $this->post(route('admin.inquiries.lead.store', $inquiry), [
            'notes' => 'Owner agreed Aug 1 move-in.',
        ]);

        $this->assertSame('Owner agreed Aug 1 move-in.', Lead::first()->notes);
    }

    public function test_it_overwrites_notes_to_null_on_empty_submission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $this->post(route('admin.inquiries.lead.store', $inquiry), [
            'notes' => '',
        ]);

        $this->assertNull(Lead::first()->notes);
    }

    public function test_it_validates_notes_max_length(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $response = $this->post(route('admin.inquiries.lead.store', $inquiry), [
            'notes' => str_repeat('x', 2001),
        ]);

        $response->assertSessionHasErrorsIn('lead-' . $inquiry->uuid, [
            'notes' => 'Lead notes must be 2000 characters or fewer.',
        ]);
        $this->assertSame(0, Lead::count());
    }

    // ---------------------------------------------------------------------
    // Route binding
    // ---------------------------------------------------------------------

    public function test_it_404s_for_unknown_inquiry_uuid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->post('/admin/inquiries/00000000-0000-0000-0000-000000000000/lead');

        $response->assertNotFound();
    }
}
