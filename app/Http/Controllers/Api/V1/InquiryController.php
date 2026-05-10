<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InquiryStatus;
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
                'status'     => InquiryStatus::new()->value,
            ]);
        });

        return response()->json(['success' => true]);
    }
}
