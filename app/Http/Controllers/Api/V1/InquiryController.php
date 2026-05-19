<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Renter;
use App\Rules\PhMobileNumber;
use App\Support\PhMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InquiryController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->is_verified, 404);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new PhMobileNumber],
        ], [
            'name.required'  => 'Name is required.',
            'name.max'       => 'Name must be 120 characters or fewer.',
            'phone.required' => 'Phone is required.',
        ]);

        $normalizedPhone = PhMobile::normalize($validated['phone']);

        DB::transaction(function () use ($validated, $listing, $normalizedPhone) {
            $renter = Renter::firstOrCreate(
                ['phone' => $normalizedPhone],
                ['name' => $validated['name']]
            );

            Inquiry::create([
                'renter_id'  => $renter->id,
                'listing_id' => $listing->id,
            ]);
        });

        $listing->loadMissing('listingContact');

        // Deliberate deviation from CLAUDE.md's `{success: true}`-only API-write rule:
        // mobile clients have no separate success GET page and need the listing's
        // contact details to render their next screen. Web flashes these to session
        // for /inquiries-success; API ships them inline on the response body.
        return response()->json([
            'success' => true,
            'listing' => [
                'title'              => $listing->title,
                'barangay'           => Barangay::from($listing->barangay)->label,
                'contact_type_label' => $listing->contact_type ? ContactType::from($listing->contact_type)->label : null,
                'listing_contact'    => [
                    'phone' => $listing->listingContact?->phone,
                    'name'  => $listing->listingContact?->is_show_name  ? $listing->listingContact->name  : null,
                    'notes' => $listing->listingContact?->is_show_notes ? $listing->listingContact->notes : null,
                ],
            ],
        ]);
    }
}
