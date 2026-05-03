<?php

namespace App\Http\Controllers;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Rules\PhMobileNumber;
use App\Support\PhMobile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InquiryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'listing_uuid' => ['required', 'string'],
            'name'         => ['required', 'string', 'max:120'],
            'phone'        => ['required', 'string', new PhMobileNumber],
        ], [
            'listing_uuid.required' => 'Listing reference missing. Reload the page and try again.',
            'name.required'         => 'Name is required.',
            'name.max'              => 'Name must be 120 characters or fewer.',
            'phone.required'        => 'Phone is required.',
        ]);

        $listing = Listing::where('uuid', $validated['listing_uuid'])->verified()->first();
        abort_unless($listing, 404);

        $normalizedPhone = PhMobile::normalize($validated['phone']);

        DB::transaction(function () use ($validated, $listing, $normalizedPhone) {
            $renter = Renter::firstOrCreate(
                ['phone' => $normalizedPhone],
                ['name' => $validated['name']]
            );

            Inquiry::create([
                'renter_id'  => $renter->id,
                'listing_id' => $listing->id,
                'status'     => InquiryStatus::new()->value,
            ]);
        });

        return redirect()->route('inquiries.success')
            ->with('inquiry.listing_title', $listing->title);
    }

    public function success(): View
    {
        return view('inquiries.success', [
            'listingTitle' => session('inquiry.listing_title'),
        ]);
    }
}
