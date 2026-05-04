<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminInquiryFilteredResource;
use App\Http\Resources\AdminInquiryResource;
use App\Models\HandoffLock;
use App\Models\Inquiry;
use App\Support\PhMobile;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function filteredIndex(): View
    {
        $rows = Inquiry::query()
            ->where('status', InquiryStatus::new()->value)
            ->whereHas('listing', fn ($q) => $q->whereDoesntHave('activeHandoff'))
            ->with(['renter', 'listing'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10);

        return view('admin.inquiries.filtered-index', [
            'inquiries' => AdminInquiryFilteredResource::collection($rows)->response()->getData(true),
            'counts'    => $this->counts(),
        ]);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            // Browse: Eloquent paginate sorted by most-recently-touched first.
            // updated_at (not created_at) so handoffs / rejects / note edits
            // bubble back to the top — reference lookup is "what changed
            // recently?" not "what came in recently?".
            $rows = Inquiry::query()
                ->with(['renter', 'listing', 'handedOffBy', 'rejectedBy'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(10);
        } else {
            // Search: Scout against the JOINed renters + listings (see
            // Inquiry::newScoutQuery + toSearchableArray dotted keys).
            // Phone-shaped input gets normalized to E.164 so 0917… and +639…
            // both match the canonical stored form. Non-phone input passes raw.
            $searchInput = PhMobile::normalize($q) ?? $q;

            $rows = Inquiry::search($searchInput)
                ->query(fn ($qb) => $qb->with(['renter', 'listing', 'handedOffBy', 'rejectedBy']))
                ->paginate(10)
                ->appends($request->only(['q']));
        }

        return view('admin.inquiries.index', [
            'inquiries' => AdminInquiryResource::collection($rows)->response()->getData(true),
            'counts'    => $this->counts(),
            'filters'   => ['q' => $q],
        ]);
    }

    public function reject(Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->status === InquiryStatus::new()->value, 422);

        $validated = request()->validateWithBag(
            'reject-' . $inquiry->uuid,
            [
                'notes'           => 'nullable|string|max:2000',
                'renter_notes'    => 'nullable|string|max:2000',
                'is_disqualified' => 'sometimes|boolean',
            ],
            [
                'notes.max'        => 'Inquiry notes must be 2000 characters or fewer.',
                'renter_notes.max' => 'Renter notes must be 2000 characters or fewer.',
            ],
        );

        $inquiryNotes = ($validated['notes']        ?? '') !== '' ? $validated['notes']        : null;
        $renterNotes  = ($validated['renter_notes'] ?? '') !== '' ? $validated['renter_notes'] : null;

        $renterUpdate = ['notes' => $renterNotes];
        if (! empty($validated['is_disqualified'])) {
            $renterUpdate['is_qualified'] = false;
        }

        DB::transaction(function () use ($inquiry, $inquiryNotes, $renterUpdate) {
            $inquiry->update([
                'status'      => InquiryStatus::rejected()->value,
                'notes'       => $inquiryNotes,
                'rejected_at' => Carbon::now(),
                'rejected_by' => auth('admin')->id(),
            ]);

            $inquiry->renter->update($renterUpdate);
        });

        return redirect()->route('admin.filtered-inquiries.index')
            ->with('info', 'Inquiry marked as rejected.');
    }

    public function handoff(Inquiry $inquiry): RedirectResponse
    {
        abort_unless($inquiry->status === InquiryStatus::new()->value, 422);

        $listing = $inquiry->listing;
        abort_if(! $listing, 404, 'Listing no longer available.');

        $validated = request()->validateWithBag(
            'handoff-' . $inquiry->uuid,
            [
                'notes'        => 'nullable|string|max:2000',
                'renter_notes' => 'nullable|string|max:2000',
            ],
            [
                'notes.max'        => 'Inquiry notes must be 2000 characters or fewer.',
                'renter_notes.max' => 'Renter notes must be 2000 characters or fewer.',
            ],
        );

        $inquiryNotes = ($validated['notes']        ?? '') !== '' ? $validated['notes']        : null;
        $renterNotes  = ($validated['renter_notes'] ?? '') !== '' ? $validated['renter_notes'] : null;

        try {
            DB::transaction(function () use ($inquiry, $listing, $inquiryNotes, $renterNotes) {
                HandoffLock::create([
                    'inquiry_id' => $inquiry->id,
                    'listing_id' => $listing->id,
                    'created_by' => auth('admin')->id(),
                ]);

                $inquiry->update([
                    'status'        => InquiryStatus::handedOff()->value,
                    'handed_off_at' => Carbon::now(),
                    'handed_off_by' => auth('admin')->id(),
                    'notes'         => $inquiryNotes,
                ]);

                $inquiry->renter->update([
                    'notes'        => $renterNotes,
                    'is_qualified' => true,
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
            ->with('success', 'Handoff recorded — renter qualified, listing locked.');
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
