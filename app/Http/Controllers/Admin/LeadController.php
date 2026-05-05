<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLeadResource;
use App\Models\Inquiry;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = Lead::query()
            ->with(['inquiry.renter', 'inquiry.listing', 'createdBy']);

        if ($status !== null && in_array($status, LeadStatus::toValues(), true)) {
            $query->where('status', $status);
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->appends($request->only(['status']));

        return view('admin.leads.index', [
            'leads'   => AdminLeadResource::collection($rows)->response()->getData(true),
            'filters' => ['status' => $status],
        ]);
    }

    public function store(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $bag = 'lead-' . $inquiry->uuid;

        // State guards routed through ValidationException so they flash into
        // the same named bag the validation errors use — frontend reads ONE
        // bag per row, displays everything inline. ValidationException's
        // default render() does back()->withInput()->withErrors(), so notes
        // are retained automatically.
        if ($inquiry->status !== InquiryStatus::handedOff()->value) {
            throw ValidationException::withMessages([
                '_state' => 'Only handed-off inquiries can be marked as leads.',
            ])->errorBag($bag);
        }

        if ($inquiry->lead) {
            throw ValidationException::withMessages([
                '_state' => 'A lead already exists for this inquiry.',
            ])->errorBag($bag);
        }

        $validated = $request->validateWithBag(
            $bag,
            ['notes' => 'nullable|string|max:2000'],
            ['notes.max' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $notes = ($validated['notes'] ?? '') !== '' ? $validated['notes'] : null;

        Lead::create([
            'inquiry_id' => $inquiry->id,
            'created_by' => auth('admin')->id(),
            'status'     => LeadStatus::pending()->value,
            'notes'      => $notes,
        ]);

        return back()->with('success', 'Lead created — ready to send to broker.');
    }

    public function send(Request $request, Lead $lead): RedirectResponse
    {
        $bag = 'send-' . $lead->uuid;

        // State guard via ValidationException so it flashes into the same
        // named bag the validation errors use — frontend reads ONE bag per
        // row, displays everything inline. ValidationException's default
        // render() does back()->withInput()->withErrors(), so notes are
        // retained automatically. Per feedback_state_guard_via_validation_exception.md.
        if ($lead->status !== LeadStatus::pending()->value) {
            $message = match ($lead->status) {
                LeadStatus::sent()->value      => 'This lead has already been sent.',
                LeadStatus::finalized()->value => 'Cannot send a finalized lead.',
                LeadStatus::lost()->value      => 'Cannot send a lost lead.',
                default                        => 'This lead cannot be sent from its current state.',
            };

            throw ValidationException::withMessages([
                '_state' => $message,
            ])->errorBag($bag);
        }

        $validated = $request->validateWithBag(
            $bag,
            ['notes' => 'nullable|string|max:2000'],
            ['notes.max' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $notes = ($validated['notes'] ?? '') !== '' ? $validated['notes'] : null;

        DB::transaction(function () use ($lead, $notes) {
            $lead->update([
                'status'  => LeadStatus::sent()->value,
                'sent_at' => Carbon::now(),
                'notes'   => $notes,
            ]);
        });

        return back()->with('success', 'Lead marked as sent.');
    }

    public function lose(Request $request, Lead $lead): RedirectResponse
    {
        $bag = 'lose-' . $lead->uuid;

        // State guard via ValidationException so it flashes into the same
        // named bag the validation errors use — frontend reads ONE bag per
        // row, displays everything inline. ValidationException's default
        // render() does back()->withInput()->withErrors(), so notes are
        // retained automatically. Per feedback_state_guard_via_validation_exception.md.
        //
        // Two source states are valid: pending OR sent. Guard branches on
        // "neither" — distinct messages per illegal source state.
        if (
            $lead->status !== LeadStatus::pending()->value
            && $lead->status !== LeadStatus::sent()->value
        ) {
            $message = match ($lead->status) {
                LeadStatus::finalized()->value => 'Cannot mark a finalized lead as lost.',
                LeadStatus::lost()->value      => 'This lead has already been lost.',
                default                        => 'This lead cannot be marked as lost from its current state.',
            };

            throw ValidationException::withMessages([
                '_state' => $message,
            ])->errorBag($bag);
        }

        $validated = $request->validateWithBag(
            $bag,
            ['notes' => 'nullable|string|max:2000'],
            ['notes.max' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $notes = ($validated['notes'] ?? '') !== '' ? $validated['notes'] : null;

        // sent_at is intentionally NOT in the update payload — preserved
        // when flipping sent→lost. Pinned via AdminLeadLoseTest #13.
        DB::transaction(function () use ($lead, $notes) {
            $lead->update([
                'status'  => LeadStatus::lost()->value,
                'lost_at' => Carbon::now(),
                'notes'   => $notes,
            ]);
        });

        return back()->with('success', 'Lead marked as lost.');
    }

    public function finalize(Request $request, Lead $lead): RedirectResponse
    {
        $bag = 'finalize-' . $lead->uuid;

        // State guard via ValidationException so it flashes into the same
        // named bag the validation errors use — frontend reads ONE bag per
        // row, displays everything inline. ValidationException's default
        // render() does back()->withInput()->withErrors(), so notes are
        // retained automatically. Per feedback_state_guard_via_validation_exception.md.
        //
        // Single source state: sent only. Mirrors PRD-047 /send shape (one
        // legal source, three illegal). Three state-guard message branches.
        if ($lead->status !== LeadStatus::sent()->value) {
            $message = match ($lead->status) {
                LeadStatus::pending()->value   => 'Cannot finalize a pending lead.',
                LeadStatus::finalized()->value => 'This lead has already been finalized.',
                LeadStatus::lost()->value      => 'Cannot finalize a lost lead.',
                default                        => 'This lead cannot be finalized from its current state.',
            };

            throw ValidationException::withMessages([
                '_state' => $message,
            ])->errorBag($bag);
        }

        $validated = $request->validateWithBag(
            $bag,
            ['notes' => 'nullable|string|max:2000'],
            ['notes.max' => 'Lead notes must be 2000 characters or fewer.'],
        );

        $notes = ($validated['notes'] ?? '') !== '' ? $validated['notes'] : null;

        // sent_at is intentionally NOT in the update payload — preserved
        // when flipping sent→finalized. Pinned via AdminLeadFinalizeTest #13.
        // Mirrors AdminLeadLoseTest #13's invariant for sent→lost (FRD-048).
        DB::transaction(function () use ($lead, $notes) {
            $lead->update([
                'status'       => LeadStatus::finalized()->value,
                'finalized_at' => Carbon::now(),
                'notes'        => $notes,
            ]);
        });

        return back()->with('success', 'Lead marked as finalized.');
    }
}
