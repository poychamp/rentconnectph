<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminInquiryRejectTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $inquiry = Inquiry::factory()->create();

        $response = $this->put(route('admin.inquiries.reject', $inquiry));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $inquiry = Inquiry::factory()->create();

        $this->put(route('admin.inquiries.reject', $inquiry))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // State-machine guard — reject is only valid against status='new'.
    // Already-rejected and already-handed-off inquiries 422.
    // ---------------------------------------------------------------------

    public function test_it_aborts_with_422_when_inquiry_is_already_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->rejected()->create();

        $response = $this->put(route('admin.inquiries.reject', $inquiry));
        $response->assertStatus(422);
    }

    public function test_it_aborts_with_422_when_inquiry_is_handed_off(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $response = $this->put(route('admin.inquiries.reject', $inquiry));
        $response->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Happy path — state-companion timestamp + actor written atomically
    // with the status flip. Mirrors AdminInquiryHandoffTest's #7.
    // ---------------------------------------------------------------------

    public function test_it_flips_inquiry_status_to_rejected_with_actor_and_timestamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $now = Carbon::create(2026, 5, 4, 14, 30, 0);
        Carbon::setTestNow($now);

        $this->put(route('admin.inquiries.reject', $inquiry));

        Carbon::setTestNow();

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::rejected()->value, $inquiry->status);
        $this->assertNotNull($inquiry->rejected_at);
        $this->assertTrue(
            $inquiry->rejected_at->equalTo($now),
            'rejected_at should equal the controller-side Carbon::now() at reject time.',
        );
        $this->assertSame($admin->id, $inquiry->rejected_by);
    }

    public function test_it_redirects_to_filtered_inquiries_index_with_info_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $response = $this->put(route('admin.inquiries.reject', $inquiry));

        $response->assertRedirect(route('admin.filtered-inquiries.index'));
        $response->assertSessionHas('info', 'Inquiry marked as rejected.');
    }

    // ---------------------------------------------------------------------
    // Queue exclusion — rejected inquiry leaves the filtered queue
    // (status=new only). Untouched new inquiry still appears.
    // ---------------------------------------------------------------------

    public function test_it_excludes_the_inquiry_from_the_filtered_queue_after_reject(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiryA = Inquiry::factory()->create();
        $inquiryB = Inquiry::factory()->create();

        $this->put(route('admin.inquiries.reject', $inquiryA))
            ->assertRedirect(route('admin.filtered-inquiries.index'));

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertNotContains(
            $inquiryA->uuid,
            $uuids,
            'Rejected inquiry must be excluded from the filtered queue (status=new only).',
        );
        $this->assertContains(
            $inquiryB->uuid,
            $uuids,
            'Untouched new inquiry should still appear.',
        );
    }

    // ---------------------------------------------------------------------
    // Renter-side side-effect — drives the Rejected ×N badge data flow.
    // ---------------------------------------------------------------------

    // ---------------------------------------------------------------------
    // Notes persistence + validation — reject modal captures rejection
    // reason (per-event inquiry.notes) and durable renter.notes. Always
    // overwrites both columns from submitted values. Mirrors handoff's
    // notes pattern from PRD-042. Validation lands in named bag
    // `reject-{uuid}` (same per-row isolation pattern).
    // ---------------------------------------------------------------------

    public function test_it_persists_inquiry_notes_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $this->put(route('admin.inquiries.reject', $inquiry), [
            'notes' => 'Renter ghosted after 3 callback attempts; phone goes to voicemail.',
        ]);

        $inquiry->refresh();
        $this->assertSame(
            'Renter ghosted after 3 callback attempts; phone goes to voicemail.',
            $inquiry->notes,
        );
    }

    public function test_it_persists_renter_notes_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['notes' => null]);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.reject', $inquiry), [
            'renter_notes' => 'Budget too low for current market; ghosted callbacks.',
        ]);

        $renter->refresh();
        $this->assertSame(
            'Budget too low for current market; ghosted callbacks.',
            $renter->notes,
        );
    }

    public function test_it_overwrites_renter_notes_to_null_on_empty_submission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['notes' => 'Old notes from earlier']);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.reject', $inquiry), [
            'notes'        => '',
            'renter_notes' => '',
        ]);

        $inquiry->refresh();
        $renter->refresh();

        $this->assertNull(
            $inquiry->notes,
            'Empty submission must overwrite inquiry.notes to null.',
        );
        $this->assertNull(
            $renter->notes,
            'Empty submission must overwrite renter.notes to null (latest call wins; intentional clear).',
        );
    }

    public function test_it_validates_notes_max_length(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $response = $this->put(route('admin.inquiries.reject', $inquiry), [
            'notes'        => str_repeat('x', 2001),
            'renter_notes' => str_repeat('y', 2001),
        ]);

        $response->assertSessionHasErrors([
            'notes'        => 'Inquiry notes must be 2000 characters or fewer.',
            'renter_notes' => 'Renter notes must be 2000 characters or fewer.',
        ], null, 'reject-' . $inquiry->uuid);

        // Defense-in-depth: assert no state mutation on validation failure.
        $inquiry->refresh();
        $this->assertSame(InquiryStatus::new()->value, $inquiry->status);
        $this->assertNull($inquiry->rejected_at);
        $this->assertNull($inquiry->rejected_by);
    }

    // ---------------------------------------------------------------------
    // Disqualify checkbox — `is_disqualified=1` from the modal writes
    // `is_qualified=false` on the renter (explicit column write per path C;
    // no accessor derivation). When the checkbox is unchecked / omitted,
    // the column is NOT touched — previously-qualified renters stay
    // qualified through unrelated rejections.
    // ---------------------------------------------------------------------

    public function test_it_marks_renter_as_disqualified_when_is_disqualified_is_true(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['is_qualified' => null]);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.reject', $inquiry), [
            'is_disqualified' => '1',
        ]);

        $renter->refresh();
        $this->assertFalse(
            $renter->is_qualified,
            'Disqualify checkbox checked must write is_qualified=false explicitly.',
        );
    }

    public function test_it_preserves_previously_qualified_renter_when_disqualify_is_unchecked(): void
    {
        // User clarification: if the checkbox is unchecked and the renter
        // was previously qualified (e.g. via a prior handoff), the renter
        // stays qualified. Reject without disqualify must NOT touch the
        // is_qualified column at all.
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['is_qualified' => true]);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.reject', $inquiry));

        $renter->refresh();
        $this->assertTrue(
            $renter->is_qualified,
            'Reject without disqualify must preserve a previously-qualified renter (stays true).',
        );
    }

    public function test_it_leaves_renter_qualification_null_when_disqualify_is_omitted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['is_qualified' => null]);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.reject', $inquiry));

        $renter->refresh();
        $this->assertNull(
            $renter->is_qualified,
            'Reject without disqualify must NOT touch a null is_qualified — new renters stay in the null/no-signal state.',
        );
    }
}
