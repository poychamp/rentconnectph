<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryStatus;
use App\Models\HandoffLock;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminInquiryHandoffTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $inquiry = Inquiry::factory()->create();

        $response = $this->put(route('admin.inquiries.handoff', $inquiry));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $inquiry = Inquiry::factory()->create();

        $this->put(route('admin.inquiries.handoff', $inquiry))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // State-machine guard
    // ---------------------------------------------------------------------

    public function test_it_aborts_with_422_when_inquiry_is_already_handed_off(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();
        $beforeLockCount = HandoffLock::count();

        $response = $this->put(route('admin.inquiries.handoff', $inquiry));
        $response->assertStatus(422);

        $this->assertSame(
            $beforeLockCount,
            HandoffLock::count(),
            'No new handoff_locks row should be created when guard rejects the PUT.',
        );
    }

    public function test_it_aborts_with_422_when_inquiry_is_dead(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->dead()->create();

        $response = $this->put(route('admin.inquiries.handoff', $inquiry));
        $response->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Listing guard
    // ---------------------------------------------------------------------

    public function test_it_aborts_with_404_when_listing_no_longer_exists(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();
        // Soft-delete (not forceDelete) — cascadeOnDelete on the FK would
        // hard-delete the inquiry too, breaking route-model binding.
        // Soft-deleted listing makes belongsTo() return null; controller
        // hits its abort_if(!$listing, 404) guard.
        $inquiry->listing->delete();

        $response = $this->put(route('admin.inquiries.handoff', $inquiry));
        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------------

    public function test_it_creates_a_handoff_lock_row_for_the_listing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $this->assertSame(0, HandoffLock::count());

        $this->put(route('admin.inquiries.handoff', $inquiry));

        $this->assertSame(1, HandoffLock::count());

        $lock = HandoffLock::first();
        $this->assertSame($inquiry->id, $lock->inquiry_id);
        $this->assertSame($inquiry->listing_id, $lock->listing_id);
        $this->assertSame($admin->id, $lock->created_by);
    }

    public function test_it_flips_inquiry_status_to_handed_off_with_actor_and_timestamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $now = Carbon::create(2026, 5, 3, 14, 30, 0);
        Carbon::setTestNow($now);

        $this->put(route('admin.inquiries.handoff', $inquiry));

        Carbon::setTestNow();

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::handedOff()->value, $inquiry->status);
        $this->assertNotNull($inquiry->handed_off_at);
        $this->assertTrue(
            $inquiry->handed_off_at->equalTo($now),
            'handed_off_at should equal the controller-side Carbon::now() — single hoisted $now anti-flake.',
        );
        $this->assertSame($admin->id, $inquiry->handed_off_by);
    }

    public function test_it_makes_renter_qualified_via_derived_accessor(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter = Renter::factory()->create();
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->assertFalse($renter->qualified, 'Fresh renter should not be qualified before handoff.');

        $this->put(route('admin.inquiries.handoff', $inquiry));

        $renter->refresh();
        $this->assertTrue($renter->qualified, 'Renter::qualified accessor should derive true after handoff.');
    }

    public function test_it_redirects_to_filtered_inquiries_index_with_success_flash(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $response = $this->put(route('admin.inquiries.handoff', $inquiry));

        $response->assertRedirect(route('admin.filtered-inquiries.index'));
        $response->assertSessionHas('success', 'Handoff recorded — renter qualified, listing locked.');
    }

    public function test_it_excludes_the_listing_from_the_filtered_queue_after_handoff(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listingX = Listing::factory()->verified()->create();
        $listingY = Listing::factory()->verified()->create();

        $inquiryA = Inquiry::factory()->for($listingX)->create();
        $inquiryB = Inquiry::factory()->for($listingX)->create();
        $inquiryC = Inquiry::factory()->for($listingY)->create();

        $this->put(route('admin.inquiries.handoff', $inquiryA))
            ->assertRedirect(route('admin.filtered-inquiries.index'));

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertNotContains(
            $inquiryB->uuid,
            $uuids,
            'Inquiry B (same listing as handed-off A) must be excluded — listing-level lock blocks the queue.',
        );
        $this->assertContains(
            $inquiryC->uuid,
            $uuids,
            'Inquiry C (different listing) should still appear.',
        );
    }

    // ---------------------------------------------------------------------
    // Race protection — UNIQUE on handoff_locks.listing_id closes the race
    // that the prior app-layer exists()-then-create() check loses.
    // ---------------------------------------------------------------------

    public function test_it_returns_409_when_listing_already_has_an_active_handoff_lock(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->verified()->create();
        $inquiryA = Inquiry::factory()->for($listing)->create();
        $inquiryB = Inquiry::factory()->for($listing)->create();

        HandoffLock::create([
            'inquiry_id' => $inquiryA->id,
            'listing_id' => $listing->id,
            'created_by' => $admin->id,
        ]);

        $beforeLockCount = HandoffLock::count();

        $response = $this->put(route('admin.inquiries.handoff', $inquiryB));
        $response->assertStatus(409);

        $inquiryB->refresh();
        $this->assertSame(
            InquiryStatus::new()->value,
            $inquiryB->status,
            'Inquiry B must remain status=new — transaction must roll back on race-loss.',
        );
        $this->assertSame(
            $beforeLockCount,
            HandoffLock::count(),
            'No additional lock row should land on race-loss.',
        );
    }

    public function test_it_returns_409_when_concurrent_handoff_attempts_collide(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listing = Listing::factory()->verified()->create();
        $inquiryA = Inquiry::factory()->for($listing)->create();
        $inquiryB = Inquiry::factory()->for($listing)->create();

        // Direct insert simulates a concurrent commit landing first.
        // PHP test runner is single-threaded, but the controller's catch
        // path on QueryException (UNIQUE-violation, MySQL=1062 / SQLite=19)
        // is what we're exercising here.
        HandoffLock::create([
            'inquiry_id' => $inquiryA->id,
            'listing_id' => $listing->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->put(route('admin.inquiries.handoff', $inquiryB));
        $response->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Notes persistence + validation (PRD-042) — handoff modal captures
    // per-event inquiry.notes and durable renter.notes. Always overwrites
    // both columns from submitted values. Empty submission = intentional
    // clear (latest qualifying call wins).
    // ---------------------------------------------------------------------

    public function test_it_persists_inquiry_notes_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->create();

        $this->put(route('admin.inquiries.handoff', $inquiry), [
            'notes' => 'Negotiated 6-month lease, 2-month deposit, renter agreed.',
        ]);

        $inquiry->refresh();
        $this->assertSame(
            'Negotiated 6-month lease, 2-month deposit, renter agreed.',
            $inquiry->notes,
        );
    }

    public function test_it_persists_renter_notes_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['notes' => null]);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.handoff', $inquiry), [
            'renter_notes' => 'Works graveyard shift at BPO; needs late check-in OK.',
        ]);

        $renter->refresh();
        $this->assertSame(
            'Works graveyard shift at BPO; needs late check-in OK.',
            $renter->notes,
        );
    }

    public function test_it_overwrites_renter_notes_to_null_on_empty_submission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $renter  = Renter::factory()->create(['notes' => 'Old notes from earlier']);
        $inquiry = Inquiry::factory()->for($renter)->create();

        $this->put(route('admin.inquiries.handoff', $inquiry), [
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

        $response = $this->put(route('admin.inquiries.handoff', $inquiry), [
            'notes'        => str_repeat('x', 2001),
            'renter_notes' => str_repeat('y', 2001),
        ]);

        $response->assertSessionHasErrors([
            'notes'        => 'Inquiry notes must be 2000 characters or fewer.',
            'renter_notes' => 'Renter notes must be 2000 characters or fewer.',
        ], null, 'handoff-' . $inquiry->uuid);

        // Defense-in-depth: assert no state mutation on validation failure.
        $inquiry->refresh();
        $this->assertSame(InquiryStatus::new()->value, $inquiry->status);
        $this->assertSame(0, HandoffLock::where('inquiry_id', $inquiry->id)->count());
    }
}
