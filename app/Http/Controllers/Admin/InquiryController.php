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
            // Search: LIKE across renter (name/phone), listing title, and listing
            // contact phone. Phone-shaped input normalizes to E.164 so 0917… and
            // +639… both match the canonical stored form; non-phone input passes
            // raw. whereHas('listing') keeps the soft-delete + orphan guard (see
            // the browse path); the nested whereHas relations apply their own
            // SoftDeletes scopes, so matches only surface for live records.
            $searchInput = PhMobile::normalize($q) ?? $q;
            $like = '%' . $searchInput . '%';

            $rows = Inquiry::query()
                ->whereHas('listing')
                ->where(function ($w) use ($like) {
                    $w->whereHas('renter', fn ($r) => $r->where('name', 'like', $like)->orWhere('phone', 'like', $like))
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', $like))
                        ->orWhereHas('listing.listingContact', fn ($c) => $c->where('phone', 'like', $like)->orWhere('name', 'like', $like));
                })
                ->with(['renter', 'listing.listingContact'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(10)
                ->appends($request->only(['q']));
        }

        return view('admin.inquiries.index', [
            'inquiries' => AdminInquiryResource::collection($rows)->response()->getData(true),
            'filters'   => ['q' => $q],
        ]);
    }
}
