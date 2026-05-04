<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLeadResource;
use App\Models\Inquiry;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
