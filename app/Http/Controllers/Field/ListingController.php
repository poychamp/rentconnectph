<?php

namespace App\Http\Controllers\Field;

use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Http\Controllers\Controller;
use App\Http\Resources\FieldListingDetailResource;
use App\Http\Resources\FieldListingRequestVerificationResource;
use App\Http\Resources\FieldListingResource;
use App\Http\Resources\FieldPriorityListingResource;
use App\Http\Resources\FieldSubmittedListingResource;
use App\Http\Resources\FieldVerifiedListingResource;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $userId = auth('admin')->id();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            // Scout path — Algolia (prod) or database driver (local/tests).
            // No orderBy possible against Algolia; results render in Scout's
            // relevance order. Drag handles hide on the frontend during search.
            $rows = Listing::search($q)
                ->where('assigned_to', $userId)
                ->where('queue_status', QueueStatus::assigned()->value)
                ->query(fn ($eloquent) => $eloquent->with('displayImage'))
                ->paginate(10);

            $isSearching = true;
        } else {
            $rows = Listing::with('displayImage')
                ->forOfficer($userId)
                ->orderBy('assigned_at')
                ->orderBy('id')
                ->paginate(10);

            $isSearching = false;
        }

        return view('field.listings', [
            'listings' => FieldListingResource::collection($rows)
                ->response()
                ->getData(true),
            'q' => $q,
            'isSearching' => $isSearching,
        ]);
    }

    public function edit(Listing $listing): View
    {
        $userId = auth('admin')->id();

        abort_unless(
            $listing->assigned_to === $userId
                && $listing->queue_status === QueueStatus::assigned()->value,
            404,
        );

        $listing->load(
            ['images' => fn ($q) => $q->orderBy('sort_order')],
            'displayImage',
            'amenities',
        );

        return view('field.listings.edit', [
            'listing'      => (new FieldListingDetailResource($listing))->resolve(),
            'listingTypes' => collect(ListingType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ListingType::from($v)->label])
                ->all(),
            'barangays'    => collect(Barangay::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => Barangay::from($v)->label])
                ->all(),
            'sourceSites'  => collect(SourceSite::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => SourceSite::from($v)->label])
                ->all(),
            'contactTypes' => collect(ContactType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ContactType::from($v)->label])
                ->all(),
            'amenities'    => Amenity::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $userId = auth('admin')->id();

        abort_unless(
            $listing->assigned_to === $userId
                && $listing->queue_status === QueueStatus::assigned()->value,
            404,
        );

        $existingIds = $listing->images()->pluck('id')->all();

        $validated = $request->validate([
            'title'                => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string'],
            'listing_type'         => ['nullable', 'string', Rule::in(ListingType::toValues())],
            'price_monthly'        => ['nullable', 'integer', 'min:1'],
            'barangay'             => ['nullable', 'string', Rule::in(Barangay::toValues())],
            'beds'                 => ['nullable', 'integer', 'min:0', 'max:20'],
            'baths'                => ['nullable', 'integer', 'min:0', 'max:20'],
            'sqm'                  => ['nullable', 'integer', 'min:1'],
            'latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'numeric', 'between:-180,180'],
            'directions'           => ['required', 'string', 'max:500'],
            'amenities'            => ['nullable', 'array', 'max:50'],
            'amenities.*'          => ['integer', 'exists:amenities,id'],
            'photos'               => ['nullable', 'array', 'max:20'],
            'photos.*'             => ['array'],
            'photos.*.existing_id' => ['nullable', 'integer', Rule::in($existingIds)],
            'photos.*.key'         => ['nullable', 'string', 'starts_with:tmp/'],
            'photos.*.name'        => ['nullable', 'string'],
            'photos.*.size'        => ['nullable', 'integer'],
        ], [
            'title.required'           => 'Title is required.',
            'title.max'                => 'Title is too long (max 200 characters).',
            'listing_type.in'          => 'Invalid listing type.',
            'price_monthly.min'        => 'Monthly rent must be at least ₱1.',
            'barangay.in'              => 'Invalid barangay.',
            'sqm.min'                  => 'Floor area must be at least 1 sqm.',
            'photos.max'               => 'Maximum 20 photos allowed.',
            'photos.*.key.starts_with' => 'Invalid photo key.',
            'amenities.*.exists'       => "One or more selected amenities don't exist.",
            'latitude.between'         => 'Latitude must be between -90 and 90.',
            'longitude.between'        => 'Longitude must be between -180 and 180.',
            'directions.required'      => 'Directions are required.',
            'directions.max'           => 'Directions are too long (max 500 characters).',
        ]);

        $photos = $request->input('photos', []);

        foreach ($photos as $i => $photo) {
            $hasExisting = isset($photo['existing_id']);
            $hasKey      = isset($photo['key']) && $photo['key'] !== '';
            if ($hasExisting === $hasKey) {
                throw ValidationException::withMessages([
                    "photos.{$i}" => 'Each photo must reference an existing photo or a new upload, not both or neither.',
                ]);
            }
        }

        $submittedExistingIds = collect($photos)->pluck('existing_id')->filter()->all();
        $removedImageIds = array_values(array_diff($existingIds, $submittedExistingIds));
        $removedImageUrls = ListingImage::whereIn('id', $removedImageIds)->pluck('url')->all();

        DB::transaction(function () use ($request, $listing, $validated, $photos, $removedImageIds) {
            // Field-officer slice — only editable fields are persisted.
            // Calls-team-locked (contact_phone, contact_type, source_site,
            // source_url, verification_notes, prequal_status) and admin-owned
            // (assigned_to, assigned_at, queue_status, is_verified,
            // verified_at) fields are silently ignored.
            $listing->update([
                'title'         => $validated['title'],
                'description'   => $validated['description'] ?? null,
                'type'          => $validated['listing_type'] ?? null,
                'price_monthly' => $validated['price_monthly'] ?? null,
                'barangay'      => $validated['barangay'] ?? null,
                'beds'          => $validated['beds'] ?? null,
                'baths'         => $validated['baths'] ?? null,
                'sqm'           => $validated['sqm'] ?? null,
                'latitude'      => $validated['latitude'] ?? null,
                'longitude'     => $validated['longitude'] ?? null,
                'directions'    => $validated['directions'] ?? null,
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

            if (! empty($orderedImageIds)) {
                $listing->update(['display_image_id' => $orderedImageIds[0]]);
            }
            $listing->amenities()->sync($validated['amenities'] ?? []);

            ListingLifecycleEvent::create([
                'listing_id' => $listing->id,
                'event_type' => 'updated',
                'actor_id'   => auth('admin')->id(),
                'notes'      => json_encode(
                    $request->except(['_token', '_method']),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        });

        foreach ($photos as $photo) {
            if (! isset($photo['key'])) continue;
            try {
                Storage::disk('s3')->delete($photo['key']);
            } catch (\Throwable $e) {
                // Swallow.
            }
        }

        foreach ($removedImageUrls as $url) {
            try {
                $key = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
                if ($key) Storage::disk('s3')->delete($key);
            } catch (\Throwable $e) {
                // Swallow.
            }
        }

        $redirectRoute = $request->query('from') === 'priority'
            ? 'field.priority.index'
            : 'field.listings.index';

        return redirect()
            ->route($redirectRoute)
            ->with('success', "Listing '{$listing->title}' updated.");
    }

    public function showRequestVerification(Listing $listing): View
    {
        $userId = auth('admin')->id();

        abort_unless(
            $listing->assigned_to === $userId
                && $listing->queue_status === QueueStatus::assigned()->value,
            404,
        );

        $listing->load(
            ['images' => fn ($q) => $q->orderBy('sort_order')],
            'displayImage',
            'amenities',
        );

        return view('field.listings.request-verification', [
            'listing'      => (new FieldListingRequestVerificationResource($listing))->resolve(),
            'listingTypes' => collect(ListingType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ListingType::from($v)->label])
                ->all(),
            'barangays'    => collect(Barangay::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => Barangay::from($v)->label])
                ->all(),
            'sourceSites'  => collect(SourceSite::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => SourceSite::from($v)->label])
                ->all(),
            'contactTypes' => collect(ContactType::toValues())
                ->map(fn ($v) => ['value' => $v, 'label' => ContactType::from($v)->label])
                ->all(),
            'amenities'    => Amenity::orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']),
        ]);
    }

    public function requestVerification(Request $request, Listing $listing): RedirectResponse
    {
        $userId = auth('admin')->id();

        if ($listing->assigned_to !== $userId
            || $listing->queue_status !== QueueStatus::assigned()->value) {
            throw ValidationException::withMessages([
                'listing' => 'This listing is no longer assigned to you.',
            ])->errorBag('requestVerification');
        }

        $existingIds = $listing->images()->pluck('id')->all();

        $validator = Validator::make($request->all(), [
            'title'                => ['required', 'string', 'max:200'],
            'description'          => ['nullable', 'string'],
            'listing_type'         => ['required', 'string', Rule::in(ListingType::toValues())],
            'price_monthly'        => ['nullable', 'integer', 'min:1'],
            'barangay'             => ['required', 'string', Rule::in(Barangay::toValues())],
            'beds'                 => ['nullable', 'integer', 'min:0', 'max:20'],
            'baths'                => ['nullable', 'integer', 'min:0', 'max:20'],
            'sqm'                  => ['nullable', 'integer', 'min:1'],
            'latitude'             => ['required', 'numeric', 'between:-90,90'],
            'longitude'            => ['required', 'numeric', 'between:-180,180'],
            'directions'           => ['required', 'string', 'max:500'],
            'amenities'            => ['nullable', 'array', 'max:50'],
            'amenities.*'          => ['integer', 'exists:amenities,id'],
            'photos'               => [
                'required', 'array', 'min:1', 'max:20',
                function ($attribute, $value, $fail) {
                    $hasNew = collect($value)->contains(
                        fn ($p) => is_array($p)
                            && isset($p['key'])
                            && is_string($p['key'])
                            && str_starts_with($p['key'], 'tmp/')
                    );
                    if (! $hasNew) {
                        $fail('Upload at least one new photo from your visit.');
                    }
                },
            ],
            'photos.*'             => ['array'],
            'photos.*.existing_id' => ['nullable', 'integer', Rule::in($existingIds)],
            'photos.*.key'         => ['nullable', 'string', 'starts_with:tmp/'],
            'photos.*.name'        => ['nullable', 'string'],
            'photos.*.size'        => ['nullable', 'integer'],
        ], [
            'title.required'      => 'Title is required.',
            'title.max'           => 'Title is too long (max 200 characters).',
            'directions.required' => 'Directions are required.',
            'directions.max'      => 'Directions are too long (max 500 characters).',
            'latitude.required'   => 'Drop a map pin before requesting verification.',
            'latitude.between'    => 'Latitude must be between -90 and 90.',
            'longitude.required'  => 'Drop a map pin before requesting verification.',
            'longitude.between'   => 'Longitude must be between -180 and 180.',
            'photos.required'     => 'Upload at least one new photo from your visit.',
            'photos.min'          => 'Upload at least one new photo from your visit.',
            'listing_type.required' => 'Listing type is required.',
            'listing_type.in'     => 'Invalid listing type.',
            'price_monthly.min'   => 'Monthly rent must be at least ₱1.',
            'barangay.required'   => 'Barangay is required.',
            'barangay.in'         => 'Invalid barangay.',
            'sqm.min'             => 'Floor area must be at least 1 sqm.',
            'amenities.*.exists'  => "One or more selected amenities don't exist.",
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->errorBag('requestVerification');
        }
        $validated = $validator->validated();

        $photos = $request->input('photos', []);

        foreach ($photos as $i => $photo) {
            $hasExisting = isset($photo['existing_id']);
            $hasKey      = isset($photo['key']) && $photo['key'] !== '';
            if ($hasExisting === $hasKey) {
                throw ValidationException::withMessages([
                    "photos.{$i}" => 'Each photo must reference an existing photo or a new upload, not both or neither.',
                ])->errorBag('requestVerification');
            }
        }

        $submittedExistingIds = collect($photos)->pluck('existing_id')->filter()->all();
        $removedImageIds = array_values(array_diff($existingIds, $submittedExistingIds));
        $removedImageUrls = ListingImage::whereIn('id', $removedImageIds)->pluck('url')->all();

        DB::transaction(function () use ($request, $listing, $validated, $photos, $removedImageIds) {
            $listing->update([
                'title'         => $validated['title'],
                'description'   => $validated['description'] ?? null,
                'type'          => $validated['listing_type'] ?? null,
                'price_monthly' => $validated['price_monthly'] ?? null,
                'barangay'      => $validated['barangay'] ?? null,
                'beds'          => $validated['beds'] ?? null,
                'baths'         => $validated['baths'] ?? null,
                'sqm'           => $validated['sqm'] ?? null,
                'latitude'      => $validated['latitude'],
                'longitude'     => $validated['longitude'],
                'directions'    => $validated['directions'],
                'queue_status'  => QueueStatus::visited()->value,
                'visited_at'    => Carbon::now(),
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

            if (! empty($orderedImageIds)) {
                $listing->update(['display_image_id' => $orderedImageIds[0]]);
            }
            $listing->amenities()->sync($validated['amenities'] ?? []);

            ListingLifecycleEvent::create([
                'listing_id' => $listing->id,
                'event_type' => 'updated',
                'actor_id'   => auth('admin')->id(),
                'notes'      => json_encode(
                    $request->except(['_token', '_method']),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        });

        foreach ($photos as $photo) {
            if (! isset($photo['key'])) continue;
            try {
                Storage::disk('s3')->delete($photo['key']);
            } catch (\Throwable $e) {
                // Swallow.
            }
        }

        foreach ($removedImageUrls as $url) {
            try {
                $key = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
                if ($key) Storage::disk('s3')->delete($key);
            } catch (\Throwable $e) {
                // Swallow.
            }
        }

        $redirectRoute = $request->query('from') === 'priority'
            ? 'field.priority.index'
            : 'field.listings.index';

        return redirect()
            ->route($redirectRoute)
            ->with('success', "Verification requested for '{$listing->title}'.");
    }

    public function priorityIndex(Request $request): View
    {
        $userId = auth('admin')->id();

        $rows = Listing::with('displayImage')
            ->priorityForOfficer($userId)
            ->orderBy('field_priority_order')
            ->orderBy('id')
            ->get();

        // Self-heal walk: irregular rows (null OR duplicate field_priority_order)
        // get appended past max in a single transaction. Idempotent on regular state.
        $seen = [];
        $kept = collect();
        $irregular = collect();
        foreach ($rows as $row) {
            if ($row->field_priority_order === null
                || isset($seen[$row->field_priority_order])) {
                $irregular->push($row);
            } else {
                $seen[$row->field_priority_order] = true;
                $kept->push($row);
            }
        }

        if ($irregular->isNotEmpty()) {
            $maxOrder = empty($seen) ? 0 : max(array_keys($seen));
            DB::transaction(function () use ($irregular, $maxOrder) {
                foreach ($irregular as $i => $listing) {
                    $listing->update(['field_priority_order' => $maxOrder + 1 + $i]);
                }
            });
            $rows = $kept->concat($irregular);
        }

        return view('field.priority', [
            'priority' => FieldPriorityListingResource::collection($rows)
                ->response()
                ->getData(true),
        ]);
    }

    public function preview(Request $request, Listing $listing): View
    {
        $userId = auth('admin')->id();

        abort_unless(
            $listing->assigned_to === $userId
                && $listing->queue_status === QueueStatus::visited()->value,
            404,
        );

        $listing->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'amenities',
        ]);

        $from = $request->query('from');
        if (! in_array($from, ['submitted', 'verified'], true)) {
            $from = 'submitted';
        }

        return view('field.listings.preview', [
            'listing' => $listing,
            'from'    => $from,
        ]);
    }

    public function submittedIndex(Request $request): View
    {
        $userId = auth('admin')->id();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $rows = Listing::search($q)
                ->where('assigned_to', $userId)
                ->where('queue_status', QueueStatus::visited()->value)
                ->where('is_verified', 0)
                ->query(fn ($eloquent) => $eloquent->with('displayImage'))
                ->paginate(10);

            $isSearching = true;
        } else {
            $rows = Listing::with('displayImage')
                ->submittedByOfficer($userId)
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->paginate(10);

            $isSearching = false;
        }

        return view('field.submitted-listings', [
            'submitted'   => FieldSubmittedListingResource::collection($rows)
                ->response()
                ->getData(true),
            'q'           => $q,
            'isSearching' => $isSearching,
        ]);
    }

    public function verifiedIndex(Request $request): View
    {
        $userId = auth('admin')->id();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $rows = Listing::search($q)
                ->where('assigned_to', $userId)
                ->where('queue_status', QueueStatus::visited()->value)
                ->where('is_verified', true)
                ->query(fn ($eloquent) => $eloquent->with('displayImage'))
                ->paginate(10);

            $isSearching = true;
        } else {
            $rows = Listing::with('displayImage')
                ->verifiedByOfficer($userId)
                ->orderByDesc('verified_at')
                ->orderByDesc('id')
                ->paginate(10);

            $isSearching = false;
        }

        return view('field.verified-listings', [
            'verified'    => FieldVerifiedListingResource::collection($rows)
                ->response()
                ->getData(true),
            'q'           => $q,
            'isSearching' => $isSearching,
        ]);
    }
}
