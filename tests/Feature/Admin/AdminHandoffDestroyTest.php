<?php

namespace Tests\Feature\Admin;

use App\Enums\InquiryStatus;
use App\Models\HandoffLock;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SeedDatabaseAfterRefresh;
use Tests\TestCase;

class AdminHandoffDestroyTest extends TestCase
{
    use RefreshDatabase, SeedDatabaseAfterRefresh;

    // ---------------------------------------------------------------------
    // Auth / permission
    // ---------------------------------------------------------------------

    public function test_it_redirects_guests(): void
    {
        $lock = HandoffLock::factory()->create();

        $response = $this->delete(route('admin.handoffs.destroy', $lock));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_it_forbids_field_officer(): void
    {
        $field = User::factory()->field()->create();
        $this->actingAs($field, 'admin');

        $lock = HandoffLock::factory()->create();

        $this->delete(route('admin.handoffs.destroy', $lock))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Happy path — release deletes the lock; inquiry stays terminal.
    // ---------------------------------------------------------------------

    public function test_it_releases_the_lock_when_super_admin_deletes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $lock = HandoffLock::create([
            'inquiry_id' => $inquiry->id,
            'listing_id' => $inquiry->listing_id,
            'created_by' => $admin->id,
        ]);

        $lockId    = $lock->id;
        $inquiryId = $inquiry->id;

        $response = $this->delete(route('admin.handoffs.destroy', $lock));

        $this->assertNull(
            HandoffLock::find($lockId),
            'Lock row should be hard-deleted on release.',
        );

        $inquiry->refresh();
        $this->assertSame(
            InquiryStatus::handedOff()->value,
            $inquiry->status,
            'Inquiry status must remain handed_off — release does NOT revert the terminal status.',
        );

        $response->assertRedirect(route('admin.handoffs.index'));
        $response->assertSessionHas('success', 'Handoff released — listing returned to the queue.');
    }

    // ---------------------------------------------------------------------
    // End-to-end integration — released listing re-enters filtered queue.
    // ---------------------------------------------------------------------

    public function test_it_makes_a_new_inquiry_on_the_released_listing_appear_in_the_filtered_queue(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $listingX = Listing::factory()->verified()->create();

        $inquiryA = Inquiry::factory()->handedOff()->for($listingX)->create();

        $lock = HandoffLock::create([
            'inquiry_id' => $inquiryA->id,
            'listing_id' => $listingX->id,
            'created_by' => $admin->id,
        ]);

        $this->delete(route('admin.handoffs.destroy', $lock))
            ->assertRedirect(route('admin.handoffs.index'));

        // Sequence matters: new inquiry created AFTER the release so the
        // filtered queue is being asked "is this listing currently locked?"
        // post-release. If activeHandoff() exclusion still fired, $inquiryB
        // would be invisible.
        $inquiryB = Inquiry::factory()->for($listingX)->create();

        $response = $this->get(route('admin.filtered-inquiries.index'));
        $response->assertOk();

        $uuids = array_map(fn ($r) => $r['uuid'], $response->viewData('inquiries')['data']);

        $this->assertContains(
            $inquiryB->uuid,
            $uuids,
            'New inquiry on released listing must appear in the filtered queue once the lock is gone.',
        );
    }

    // ---------------------------------------------------------------------
    // No soft-delete residue — destroy is hard delete per FRD-039
    // lock-table-pattern (HandoffLock has no SoftDeletes trait). Default
    // Eloquent find() doesn't see soft-deleted rows, so a stronger
    // assertion: hit the raw DB table to confirm no row remains under any
    // scope. Locks the invariant against accidental drift if someone
    // adds SoftDeletes later — the route binding would silently start
    // hiding "released" rows that were really soft-deleted.
    // ---------------------------------------------------------------------

    public function test_it_hard_deletes_the_lock_with_no_soft_delete_residue(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $inquiry = Inquiry::factory()->handedOff()->create();

        $lock = HandoffLock::create([
            'inquiry_id' => $inquiry->id,
            'listing_id' => $inquiry->listing_id,
            'created_by' => $admin->id,
        ]);

        $lockId = $lock->id;

        $this->delete(route('admin.handoffs.destroy', $lock));

        $this->assertSame(
            0,
            DB::table('handoff_locks')->where('id', $lockId)->count(),
            'Lock should be hard-deleted — raw DB row count must be 0. HandoffLock has no SoftDeletes per FRD-039 lock-table-pattern.',
        );
    }

    // ---------------------------------------------------------------------
    // 404 path — unknown lock id
    // ---------------------------------------------------------------------

    public function test_it_returns_404_for_unknown_handoff_lock_id(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        // Hardcoded URL — cannot use route() helper for a non-existent model
        // when the route binding would fail at parameter-resolve time.
        $this->delete('/admin/handoffs/999999')->assertNotFound();
    }
}
