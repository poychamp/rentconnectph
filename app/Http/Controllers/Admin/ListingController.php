<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function adminCreate(): View
    {
        return view('admin.listings.admin-create', [
            'amenities' => Amenity::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']),
            'listingTypes' => collect(ListingType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ListingType::from($v)->label])
                ->values(),
            'barangays' => collect(Barangay::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => Barangay::from($v)->label])
                ->values(),
        ]);
    }

    public function adminStore(Request $request)
    {
        // Phase 1 wiring check — dump the submitted payload so we can confirm
        // the data shape before building real validation + persistence in Phase 2.
        // Photos arrive as Vapor S3 keys/URLs (strings), not file blobs.
        dd($request->all());
    }
}
