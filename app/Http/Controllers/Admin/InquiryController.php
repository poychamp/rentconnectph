<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminInquiryResource;
use App\Models\Inquiry;
use App\Support\PhMobile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            // Browse: Eloquent paginate sorted by most-recently-touched first.
            // updated_at (not created_at) so note edits bubble back to the top
            // — reference lookup is "what changed recently?" not "what came in
            // recently?".
            $rows = Inquiry::query()
                ->whereHas('listing')
                ->with(['renter', 'listing.listingContact'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(10);
        } else {
            // Search: Scout against the JOINed renters + listings + listing_contacts
            // (see Inquiry::newScoutQuery + toSearchableArray dotted keys).
            // Phone-shaped input gets normalized to E.164 so 0917… and +639…
            // both match the canonical stored form. Non-phone input passes raw.
            $searchInput = PhMobile::normalize($q) ?? $q;

            $rows = Inquiry::search($searchInput)
                ->query(fn ($qb) => $qb->whereHas('listing')->with(['renter', 'listing.listingContact']))
                ->paginate(10)
                ->appends($request->only(['q']));
        }

        return view('admin.inquiries.index', [
            'inquiries' => AdminInquiryResource::collection($rows)->response()->getData(true),
            'filters'   => ['q' => $q],
        ]);
    }
}
