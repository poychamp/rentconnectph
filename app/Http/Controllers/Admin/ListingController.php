<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function adminStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'listing_type'  => ['required', 'string', Rule::in(ListingType::toValues())],
            'monthly_rent'  => ['required', 'integer', 'min:1'],
            'barangay'      => ['required', 'string', Rule::in(Barangay::toValues())],
            'beds'          => ['required', 'integer', 'min:0', 'max:20'],
            'baths'         => ['required', 'integer', 'min:0', 'max:20'],
            'sqft'          => ['required', 'integer', 'min:1'],
            'latitude'      => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'     => ['nullable', 'numeric', 'between:-180,180'],
            'amenities'     => ['nullable', 'array', 'max:50'],
            'amenities.*'   => ['integer', 'exists:amenities,id'],
            'photos'        => ['required', 'array', 'min:1', 'max:20'],
            'photos.*.key'  => ['required', 'string', 'starts_with:tmp/'],
            'photos.*.name' => ['nullable', 'string'],
            'photos.*.size' => ['nullable', 'integer'],
            'is_featured'   => ['nullable', 'boolean'],
            'intent'        => ['nullable', 'string', 'in:publish,publish-and-add-another'],
        ], [
            'title.required'        => 'Title is required.',
            'title.max'             => 'Title is too long (max 200 characters).',
            'listing_type.required' => 'Listing type is required.',
            'listing_type.in'       => 'Invalid listing type.',
            'monthly_rent.required' => 'Monthly rent is required.',
            'monthly_rent.min'      => 'Monthly rent must be at least ₱1.',
            'barangay.required'     => 'Barangay is required.',
            'barangay.in'           => 'Invalid barangay.',
            'beds.required'         => 'Bedrooms is required.',
            'baths.required'        => 'Bathrooms is required.',
            'sqft.required'         => 'Floor area is required.',
            'sqft.min'              => 'Floor area must be at least 1 sqft.',
            'photos.required'       => 'At least one photo is required.',
            'photos.min'            => 'At least one photo is required.',
            'photos.max'            => 'Maximum 20 photos allowed.',
            'photos.*.key.required'    => 'Invalid photo data.',
            'photos.*.key.starts_with' => 'Invalid photo key.',
            'amenities.*.exists'    => "One or more selected amenities don't exist.",
            'latitude.between'      => 'Latitude must be between -90 and 90.',
            'longitude.between'     => 'Longitude must be between -180 and 180.',
        ]);

        $intent = $validated['intent'] ?? 'publish';

        $listing = DB::transaction(function () use ($validated) {
            $listing = Listing::create([
                'title'         => $validated['title'],
                'description'   => $validated['description'] ?? null,
                'type'          => $validated['listing_type'],     // form → DB rename
                'price_monthly' => $validated['monthly_rent'],     // form → DB rename
                'barangay'      => $validated['barangay'],
                'beds'          => $validated['beds'],
                'baths'         => $validated['baths'],
                'sqft'          => $validated['sqft'],
                'latitude'      => $validated['latitude'] ?? null,
                'longitude'     => $validated['longitude'] ?? null,
                'is_verified'   => true,
                'verified_at'   => now(),
                'is_featured'   => $validated['is_featured'] ?? false,
            ]);

            $createdImages = collect($validated['photos'])->map(function ($photo, $index) use ($listing) {
                $tmpKey       = $photo['key'];                                      // "tmp/abc-uuid"
                $filename     = basename($tmpKey);                                   // "abc-uuid"
                $permanentKey = "listings/{$listing->uuid}/{$filename}";

                $copied = Storage::disk('s3')->copy($tmpKey, $permanentKey);
                if (! $copied) {
                    // Throws so the surrounding DB::transaction rolls back.
                    throw new \RuntimeException("Failed to copy {$tmpKey} to {$permanentKey}");
                }

                return ListingImage::create([
                    'listing_id' => $listing->id,
                    'url'        => Storage::disk('s3')->url($permanentKey),
                    'sort_order' => $index,
                ]);
            });

            $listing->update(['display_image_id' => $createdImages->first()->id]);
            $listing->amenities()->sync($validated['amenities'] ?? []);

            return $listing;
        });

        // Best-effort tmp cleanup — S3 lifecycle rule is the safety net
        foreach ($validated['photos'] as $photo) {
            try {
                Storage::disk('s3')->delete($photo['key']);
            } catch (\Throwable $e) {
                // Swallow — lifecycle handles it.
            }
        }

        return match ($intent) {
            'publish-and-add-another' => redirect()
                ->route('admin.listings.admin-create')
                ->with('success', "Listing '{$listing->title}' published. Add another below."),
            default => redirect()
                // TODO: when admin.listings.show route exists, swap to:
                //   ->route('admin.listings.show', $listing->uuid)
                ->route('admin.dashboard')
                ->with('success', "Listing '{$listing->title}' published successfully."),
        };
    }
}
