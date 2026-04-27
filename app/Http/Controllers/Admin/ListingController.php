<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUnverifiedListingResource;
use App\Http\Resources\AdminVerifiedListingResource;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function edit(Listing $listing): View
    {
        $listing->load(['images' => fn ($q) => $q->orderBy('sort_order'), 'amenities']);

        return view('admin.listings.edit', [
            'listing'      => $listing,
            'listingTypes' => collect(ListingType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ListingType::from($v)->label])
                ->values(),
            'barangays'    => collect(Barangay::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => Barangay::from($v)->label])
                ->values(),
            'amenities'    => Amenity::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $existingIds = $listing->images()->pluck('id')->all();

        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string'],
            'listing_type'         => ['required', 'string', Rule::in(ListingType::toValues())],
            'monthly_rent'         => ['required', 'integer', 'min:1'],
            'barangay'             => ['required', 'string', Rule::in(Barangay::toValues())],
            'beds'                 => ['required', 'integer', 'min:0', 'max:20'],
            'baths'                => ['required', 'integer', 'min:0', 'max:20'],
            'sqft'                 => ['required', 'integer', 'min:1'],
            'latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'numeric', 'between:-180,180'],
            'amenities'            => ['nullable', 'array', 'max:50'],
            'amenities.*'          => ['integer', 'exists:amenities,id'],
            'photos'               => ['required', 'array', 'min:1', 'max:20'],
            'photos.*'             => ['array'],
            'photos.*.existing_id' => ['nullable', 'integer', Rule::in($existingIds)],
            'photos.*.key'         => ['nullable', 'string', 'starts_with:tmp/'],
            'photos.*.name'        => ['nullable', 'string'],
            'photos.*.size'        => ['nullable', 'integer'],
            'is_verified'          => ['required', 'boolean'],
            'is_featured'          => ['nullable', 'boolean'],
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
            'photos.required'          => 'At least one photo is required.',
            'photos.min'               => 'At least one photo is required.',
            'photos.max'               => 'Maximum 20 photos allowed.',
            'photos.*.key.starts_with' => 'Invalid photo key.',
            'amenities.*.exists'    => "One or more selected amenities don't exist.",
            'latitude.between'      => 'Latitude must be between -90 and 90.',
            'longitude.between'     => 'Longitude must be between -180 and 180.',
            'is_verified.required'  => 'Verified flag is required.',
        ]);

        // Validation passed — but Laravel's wildcard validator reorders nested
        // arrays in $validated, while $request->input(...) preserves the
        // original input order. Photo iteration order matters (it determines
        // sort_order + display_image_id), so we use the request input directly.
        $photos = $request->input('photos', []);

        // Each photo entry must have exactly one of {existing_id, key}.
        foreach ($photos as $i => $photo) {
            $hasExisting = isset($photo['existing_id']);
            $hasKey      = isset($photo['key']) && $photo['key'] !== '';
            if ($hasExisting === $hasKey) {
                throw ValidationException::withMessages([
                    "photos.{$i}" => 'Each photo must reference an existing photo or a new upload, not both or neither.',
                ]);
            }
        }

        // Photos missing from the submitted array were removed by the admin —
        // capture URLs now (for post-commit S3 cleanup) and IDs for row deletion.
        $submittedExistingIds = collect($photos)->pluck('existing_id')->filter()->all();
        $removedImageIds = array_values(array_diff($existingIds, $submittedExistingIds));
        $removedImageUrls = ListingImage::whereIn('id', $removedImageIds)->pluck('url')->all();

        // is_verified transition: only update verified_at on actual transitions.
        $wasVerified    = (bool) $listing->is_verified;
        $willBeVerified = (bool) $validated['is_verified'];
        $verifiedAt = match (true) {
            ! $wasVerified && $willBeVerified => Carbon::now(),
            $wasVerified && ! $willBeVerified => null,
            default                           => $listing->verified_at,
        };

        DB::transaction(function () use ($listing, $validated, $photos, $verifiedAt, $removedImageIds) {
            $listing->update([
                'title'         => $validated['title'],
                'description'   => $validated['description'] ?? null,
                'type'          => $validated['listing_type'],
                'price_monthly' => $validated['monthly_rent'],
                'barangay'      => $validated['barangay'],
                'beds'          => $validated['beds'],
                'baths'         => $validated['baths'],
                'sqft'          => $validated['sqft'],
                'latitude'      => $validated['latitude'] ?? null,
                'longitude'     => $validated['longitude'] ?? null,
                'is_verified'   => (bool) $validated['is_verified'],
                'verified_at'   => $verifiedAt,
                'is_featured'   => $validated['is_featured'] ?? false,
            ]);

            if (! empty($removedImageIds)) {
                ListingImage::whereIn('id', $removedImageIds)->delete();
            }

            $orderedImageIds = [];
            foreach ($photos as $i => $photo) {
                if (isset($photo['existing_id'])) {
                    ListingImage::where('id', $photo['existing_id'])->update(['sort_order' => $i]);
                    $orderedImageIds[] = (int) $photo['existing_id'];
                    continue;
                }

                $tmpKey       = $photo['key'];
                $filename     = basename($tmpKey);
                $permanentKey = "listings/{$listing->uuid}/{$filename}";

                $copied = Storage::disk('s3')->copy($tmpKey, $permanentKey);
                if (! $copied) {
                    throw new \RuntimeException("Failed to copy {$tmpKey} to {$permanentKey}");
                }

                $img = ListingImage::create([
                    'listing_id' => $listing->id,
                    'url'        => Storage::disk('s3')->url($permanentKey),
                    'sort_order' => $i,
                ]);
                $orderedImageIds[] = $img->id;
            }

            $listing->update(['display_image_id' => $orderedImageIds[0]]);
            $listing->amenities()->sync($validated['amenities'] ?? []);
        });

        // Best-effort tmp cleanup for new uploads (S3 lifecycle is the safety net).
        foreach ($photos as $photo) {
            if (! isset($photo['key'])) continue;
            try {
                Storage::disk('s3')->delete($photo['key']);
            } catch (\Throwable $e) {
                // Swallow — lifecycle handles it.
            }
        }

        // Best-effort S3 cleanup for removed images. URLs captured pre-transaction.
        // Failure here doesn't roll back the DB — orphan S3 files are acceptable.
        foreach ($removedImageUrls as $url) {
            try {
                $key = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
                if ($key) Storage::disk('s3')->delete($key);
            } catch (\Throwable $e) {
                // Swallow — orphan acceptable.
            }
        }

        // Origin-aware redirect: ?from=unverified returns to Unverified Listings,
        // anything else (including absent or unknown) returns to Verified Listings.
        // The `from` field is UI routing metadata, not data — don't 422 on it.
        $route = $request->input('from') === 'unverified'
            ? 'admin.unverified-listings.index'
            : 'admin.verified-listings.index';

        return redirect()
            ->route($route)
            ->with('success', "Listing '{$listing->title}' updated.");
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
                ->route('admin.verified-listings.index')
                ->with('success', "Listing '{$listing->title}' published successfully."),
        };
    }

    public function verifiedIndex(Request $request): View
    {
        $q = trim((string) $request->input('q', ''));

        $paginator = $q === ''
            ? Listing::where('is_verified', true)
                ->orderBy('updated_at', 'desc')
                ->paginate(10)
            : Listing::search($q)
                ->where('is_verified', true)
                ->paginate(10);

        $verified = AdminVerifiedListingResource::collection($paginator)
            ->response()
            ->getData(true);

        return view('admin.listings.verified-index', [
            'verified' => $verified,
        ]);
    }

    public function unverifiedIndex(Request $request): View
    {
        $q    = trim((string) $request->input('q', ''));
        $sort = $request->input('sort');
        $dir  = $request->input('dir');

        // Sort and search are mutually exclusive. Algolia (Scout) can't orderBy
        // arbitrary fields at query time — see CLAUDE.md "orderBy() is a no-op
        // against Algolia". When the user requests a sort, drop q and go through
        // Eloquent so the sort actually applies.
        $hasSort = in_array($sort, ['created_at', 'updated_at'], true)
                && in_array($dir,  ['asc',        'desc'],       true);

        if ($hasSort) {
            $paginator = Listing::where('is_verified', false)
                ->orderBy($sort, $dir)
                ->paginate(10);
        } else {
            $paginator = $q === ''
                ? Listing::where('is_verified', false)
                    ->orderBy('created_at', 'asc')
                    ->paginate(10)
                : Listing::search($q)
                    ->where('is_verified', '0')
                    ->paginate(10);
        }

        $unverified = AdminUnverifiedListingResource::collection($paginator)
            ->response()
            ->getData(true);

        return view('admin.listings.unverified-index', [
            'unverified' => $unverified,
        ]);
    }

    private function mockVerifiedListings(): array
    {
        $base = [
            [
                'id'             => 1,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0001',
                'name'           => 'Cozy 2-BR Apartment in Carmen',
                'type_label'     => 'Apartment',
                'barangay_label' => 'Carmen',
                'price'          => 8500,
                'owner'          => 'Maria Santos',
                'verified_at'    => '2026-03-26',
                'status'         => 'on_site',
                'is_featured'    => true,
            ],
            [
                'id'             => 2,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0002',
                'name'           => 'Modern Condo Unit in Macasandig',
                'type_label'     => 'Condo',
                'barangay_label' => 'Macasandig',
                'price'          => 15000,
                'owner'          => 'Juan dela Cruz',
                'verified_at'    => '2026-03-22',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 3,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0003',
                'name'           => 'Spacious House in Kauswagan',
                'type_label'     => 'House',
                'barangay_label' => 'Kauswagan',
                'price'          => 12000,
                'owner'          => 'Ana Reyes',
                'verified_at'    => '2026-03-18',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 4,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0004',
                'name'           => 'Featured Studio Unit in Lapasan',
                'type_label'     => 'Studio',
                'barangay_label' => 'Lapasan',
                'price'          => 6500,
                'owner'          => 'Carlos Bautista',
                'verified_at'    => '2026-03-15',
                'status'         => 'on_site',
                'is_featured'    => true,
            ],
            [
                'id'             => 5,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0005',
                'name'           => 'Bedspace near Xavier University',
                'type_label'     => 'Bedspacer',
                'barangay_label' => 'Pueblo de Oro',
                'price'          => 3500,
                'owner'          => 'Ramon Cruz',
                'verified_at'    => '2026-03-10',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 6,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0006',
                'name'           => 'Two-Story Family Home in Nazareth',
                'type_label'     => 'House',
                'barangay_label' => 'Nazareth',
                'price'          => 22000,
                'owner'          => 'Patricia Lim',
                'verified_at'    => '2026-03-05',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 7,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0007',
                'name'           => 'Compact Studio near SM CDO',
                'type_label'     => 'Studio',
                'barangay_label' => 'Lapasan',
                'price'          => 7200,
                'owner'          => 'Liza Mendoza',
                'verified_at'    => '2026-03-02',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 8,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0008',
                'name'           => '1-BR Apartment in Camaman-an',
                'type_label'     => 'Apartment',
                'barangay_label' => 'Camaman-an',
                'price'          => 9000,
                'owner'          => 'Diego Torres',
                'verified_at'    => '2026-02-28',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 9,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0009',
                'name'           => 'Pueblo de Oro Condo (3-BR)',
                'type_label'     => 'Condo',
                'barangay_label' => 'Pueblo de Oro',
                'price'          => 28000,
                'owner'          => 'Sofia Garcia',
                'verified_at'    => '2026-02-25',
                'status'         => 'on_site',
                'is_featured'    => true,
            ],
            [
                'id'             => 10,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000a',
                'name'           => 'Bunkbed Space for Female Students',
                'type_label'     => 'Bedspacer',
                'barangay_label' => 'Carmen',
                'price'          => 2800,
                'owner'          => 'Rita Aquino',
                'verified_at'    => '2026-02-22',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 11,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000b',
                'name'           => 'Renovated Townhouse in Bulua',
                'type_label'     => 'House',
                'barangay_label' => 'Bulua',
                'price'          => 18500,
                'owner'          => 'Marco Villanueva',
                'verified_at'    => '2026-02-18',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 12,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000c',
                'name'           => '2-BR Apartment near Capitol University',
                'type_label'     => 'Apartment',
                'barangay_label' => 'Gusa',
                'price'          => 11000,
                'owner'          => 'Elena Pascual',
                'verified_at'    => '2026-02-15',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 13,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000d',
                'name'           => 'Loft-Style Studio in Macasandig',
                'type_label'     => 'Studio',
                'barangay_label' => 'Macasandig',
                'price'          => 9500,
                'owner'          => 'Andres Flores',
                'verified_at'    => '2026-02-12',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 14,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000e',
                'name'           => 'Family House in Kauswagan (4-BR)',
                'type_label'     => 'House',
                'barangay_label' => 'Kauswagan',
                'price'          => 25000,
                'owner'          => 'Beatriz Domingo',
                'verified_at'    => '2026-02-08',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 15,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a000f',
                'name'           => 'Modern 1-BR Condo in Lapasan',
                'type_label'     => 'Condo',
                'barangay_label' => 'Lapasan',
                'price'          => 13500,
                'owner'          => 'Joel Ramirez',
                'verified_at'    => '2026-02-05',
                'status'         => 'on_site',
                'is_featured'    => true,
            ],
            [
                'id'             => 16,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0010',
                'name'           => 'Coed Bedspace in Patag',
                'type_label'     => 'Bedspacer',
                'barangay_label' => 'Patag',
                'price'          => 3200,
                'owner'          => 'Vicente Cruz',
                'verified_at'    => '2026-02-01',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 17,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0011',
                'name'           => 'Studio Apartment in Carmen Heights',
                'type_label'     => 'Studio',
                'barangay_label' => 'Carmen',
                'price'          => 7800,
                'owner'          => 'Camila Tan',
                'verified_at'    => '2026-01-28',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 18,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0012',
                'name'           => 'Quiet 3-BR House in Nazareth',
                'type_label'     => 'House',
                'barangay_label' => 'Nazareth',
                'price'          => 19500,
                'owner'          => 'Felix Navarro',
                'verified_at'    => '2026-01-24',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 19,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0013',
                'name'           => 'Premium Condo Unit in Pueblo',
                'type_label'     => 'Condo',
                'barangay_label' => 'Pueblo de Oro',
                'price'          => 32000,
                'owner'          => 'Isabella Reyes',
                'verified_at'    => '2026-01-20',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 20,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0014',
                'name'           => 'Single-Bed Lodging in Macasandig',
                'type_label'     => 'Bedspacer',
                'barangay_label' => 'Macasandig',
                'price'          => 2500,
                'owner'          => 'Eduardo Lim',
                'verified_at'    => '2026-01-16',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 21,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0015',
                'name'           => 'Roomy 2-BR Apartment in Gusa',
                'type_label'     => 'Apartment',
                'barangay_label' => 'Gusa',
                'price'          => 10500,
                'owner'          => 'Marisol Bautista',
                'verified_at'    => '2026-01-12',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 22,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0016',
                'name'           => 'Spacious Family Home in Bulua',
                'type_label'     => 'House',
                'barangay_label' => 'Bulua',
                'price'          => 21000,
                'owner'          => 'Octavio Mendez',
                'verified_at'    => '2026-01-08',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 23,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0017',
                'name'           => 'Studio with Balcony in Lapasan',
                'type_label'     => 'Studio',
                'barangay_label' => 'Lapasan',
                'price'          => 8200,
                'owner'          => 'Teresa Aguilar',
                'verified_at'    => '2026-01-04',
                'status'         => 'hidden',
                'is_featured'    => false,
            ],
            [
                'id'             => 24,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0018',
                'name'           => 'Affordable 1-BR Apartment in Camaman-an',
                'type_label'     => 'Apartment',
                'barangay_label' => 'Camaman-an',
                'price'          => 7500,
                'owner'          => 'Hector Salazar',
                'verified_at'    => '2025-12-30',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
            [
                'id'             => 25,
                'uuid'           => '019dc5d9-ff77-7381-9755-e1006f2a0019',
                'name'           => 'Bedspace for Working Professionals',
                'type_label'     => 'Bedspacer',
                'barangay_label' => 'Carmen',
                'price'          => 3800,
                'owner'          => 'Lourdes Castillo',
                'verified_at'    => '2025-12-26',
                'status'         => 'on_site',
                'is_featured'    => false,
            ],
        ];

        return array_merge($base, $this->generateMockListings(start: 26, count: 75));
    }

    private function generateMockListings(int $start, int $count): array
    {
        $titlePrefixes = ['Cozy', 'Modern', 'Spacious', 'Renovated', 'Premium', 'Affordable', 'Quiet', 'Bright', 'Newly Built', 'Charming'];
        $types = [
            ['label' => 'Apartment', 'noun' => 'Apartment'],
            ['label' => 'Studio',    'noun' => 'Studio'],
            ['label' => 'House',     'noun' => 'House'],
            ['label' => 'Condo',     'noun' => 'Condo Unit'],
            ['label' => 'Bedspacer', 'noun' => 'Bedspace'],
        ];
        $barangays = ['Carmen', 'Macasandig', 'Kauswagan', 'Lapasan', 'Pueblo de Oro', 'Nazareth', 'Bulua', 'Gusa', 'Patag', 'Camaman-an'];
        $owners = [
            'Maria Santos', 'Juan dela Cruz', 'Ana Reyes', 'Carlos Bautista', 'Ramon Cruz',
            'Patricia Lim', 'Liza Mendoza', 'Diego Torres', 'Sofia Garcia', 'Rita Aquino',
            'Marco Villanueva', 'Elena Pascual', 'Andres Flores', 'Beatriz Domingo', 'Joel Ramirez',
        ];

        $startDate = strtotime('2025-12-22');
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $id       = $start + $i;
            $type     = $types[$i % count($types)];
            $barangay = $barangays[$i % count($barangays)];
            $prefix   = $titlePrefixes[$i % count($titlePrefixes)];

            $rows[] = [
                'id'             => $id,
                'uuid'           => sprintf('019dc5d9-ff77-7381-9755-e1006f2a%04x', $id),
                'name'           => "{$prefix} {$type['noun']} in {$barangay}",
                'type_label'     => $type['label'],
                'barangay_label' => $barangay,
                'price'          => 5000 + (($i * 1500) % 25000),
                'owner'          => $owners[$i % count($owners)],
                'verified_at'    => date('Y-m-d', $startDate - $i * 4 * 86400),
                'status'         => $i % 4 === 3 ? 'hidden' : 'on_site',
                'is_featured'    => $i % 7 === 0,
            ];
        }

        return $rows;
    }
}
