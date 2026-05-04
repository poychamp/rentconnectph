<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminInquiryResource;
use App\Models\HandoffLock;
use App\Models\Inquiry;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function filteredIndex(): View
    {
        $rows = Inquiry::query()
            ->where('status', InquiryStatus::new()->value)
            ->whereHas('listing', fn ($q) => $q->whereDoesntHave('activeHandoff'))
            ->with(['renter' => fn ($q) => $q->withCount('deadInquiries'), 'listing'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10);

        return view('admin.inquiries.filtered-index', [
            'inquiries' => AdminInquiryResource::collection($rows)->response()->getData(true),
            'counts'    => $this->counts(),
        ]);
    }

    public function reject(Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->status === InquiryStatus::new()->value, 422);

        $inquiry->update(['status' => InquiryStatus::dead()->value]);

        return redirect()->route('admin.filtered-inquiries.index')
            ->with('flash', ['type' => 'info', 'message' => 'Inquiry marked as dead.']);
    }

    public function handoff(Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->status === InquiryStatus::new()->value, 422);

        $listing = $inquiry->listing;
        abort_if(! $listing, 404, 'Listing no longer available.');

        try {
            DB::transaction(function () use ($inquiry, $listing) {
                HandoffLock::create([
                    'inquiry_id' => $inquiry->id,
                    'listing_id' => $listing->id,
                    'created_by' => auth('admin')->id(),
                ]);

                $inquiry->update([
                    'status'        => InquiryStatus::handedOff()->value,
                    'handed_off_at' => Carbon::now(),
                    'handed_off_by' => auth('admin')->id(),
                ]);
            });
        } catch (QueryException $e) {
            // UNIQUE on handoff_locks.listing_id triggered — race lost.
            // MySQL: SQLSTATE[23000] errorInfo[1] === 1062.
            // SQLite (test env): SQLSTATE[23000] errorInfo[1] === 19.
            $code = $e->errorInfo[1] ?? null;
            if (in_array($code, [1062, 19], true)) {
                abort(409, 'This listing already has an active handoff.');
            }
            throw $e;
        }

        return redirect()->route('admin.filtered-inquiries.index')
            ->with('flash', ['type' => 'success', 'message' => 'Handoff recorded — renter qualified, listing locked.']);
    }

    private function counts(): array
    {
        return [
            'filtered' => Inquiry::query()
                ->where('status', InquiryStatus::new()->value)
                ->whereHas('listing', fn ($q) => $q->whereDoesntHave('activeHandoff'))
                ->count(),
            'all'      => Inquiry::count(),
        ];
    }
}
