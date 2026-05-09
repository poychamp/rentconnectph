<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Enums\AppPermission;
use App\Enums\Barangay;
use App\Enums\ContactType;
use App\Enums\ListingType;
use App\Enums\QueueStatus;
use App\Enums\SourceSite;
use App\Http\Controllers\Controller;
use App\Http\Resources\FieldListingDetailResource;
use App\Http\Resources\FieldListingResource;
use App\Http\Resources\FieldPriorityListingResource;
use App\Http\Resources\FieldSubmittedListingResource;
use App\Models\Amenity;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function queued(Request $request): JsonResponse
    {
        $user = $request->user();

        // Spatie permissions are admin-guard-scoped; Sanctum auths under its own
        // guard, so `can:` / `permission:` middleware can't see them. Defense-in-depth
        // for users whose role was revoked after token issuance.
        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        $userId = $user->id;
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $rows = Listing::search($q)
                ->where('assigned_to', $userId)
                ->where('queue_status', QueueStatus::assigned()->value)
                ->query(fn ($eloquent) => $eloquent->with('displayImage'))
                ->paginate(10);
        } else {
            $rows = Listing::with('displayImage')
                ->forOfficer($userId)
                ->orderBy('assigned_at')
                ->orderBy('id')
                ->paginate(10);
        }

        return FieldListingResource::collection($rows)
            ->response();
    }

    public function submitted(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        $userId = $user->id;
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $rows = Listing::search($q)
                ->where('assigned_to', $userId)
                ->where('queue_status', QueueStatus::visited()->value)
                ->where('is_verified', 0)
                ->query(fn ($eloquent) => $eloquent->with('displayImage'))
                ->paginate(10);
        } else {
            $rows = Listing::with('displayImage')
                ->submittedByOfficer($userId)
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->paginate(10);
        }

        return FieldSubmittedListingResource::collection($rows)
            ->response();
    }

    public function priority(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        $userId = $user->id;

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

        return FieldPriorityListingResource::collection($rows)->response();
    }

    public function prioritySort(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ]);

        $userId = $user->id;
        $order  = $request->input('order');

        $listings = Listing::priorityForOfficer($userId)
            ->whereIn('uuid', $order)
            ->get()
            ->keyBy('uuid');

        if ($listings->count() !== count($order)) {
            throw ValidationException::withMessages([
                'order' => 'One or more listings are not in your priority pool.',
            ]);
        }

        DB::transaction(function () use ($listings, $order) {
            foreach ($order as $i => $uuid) {
                $listings[$uuid]->update(['field_priority_order' => $i + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function show(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        // Anti-enumeration: 404 (not 403) for listings not assigned to the caller.
        // Covers BOTH queue_status=assigned and queue_status=visited so one detail
        // screen serves the whole field workflow.
        abort_unless($listing->assigned_to === $user->id, 404);

        $listing->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'displayImage',
            'amenities',
        ]);

        return response()->json([
            'listing'  => (new FieldListingDetailResource($listing))->resolve(),
            'catalogs' => [
                'listing_types' => $this->enumCatalog(ListingType::class),
                'barangays'     => $this->enumCatalog(Barangay::class),
                'source_sites'  => $this->enumCatalog(SourceSite::class),
                'contact_types' => $this->enumCatalog(ContactType::class),
                'amenities'     => Amenity::orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'icon'])
                    ->map(fn ($a) => [
                        'id'   => $a->id,
                        'name' => $a->name,
                        'slug' => $a->slug,
                        'icon' => $a->icon,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        abort_unless(
            $listing->assigned_to === $user->id
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
            'verification_notes'   => ['nullable', 'string', 'max:2000'],
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
            'verification_notes.max'   => 'Verification notes must be 2000 characters or fewer.',
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

        DB::transaction(function () use ($request, $user, $listing, $validated, $photos, $removedImageIds) {
            $listing->update([
                'title'              => $validated['title'],
                'description'        => $validated['description'] ?? null,
                'type'               => $validated['listing_type'] ?? null,
                'price_monthly'      => $validated['price_monthly'] ?? null,
                'barangay'           => $validated['barangay'] ?? null,
                'beds'               => $validated['beds'] ?? null,
                'baths'              => $validated['baths'] ?? null,
                'sqm'                => $validated['sqm'] ?? null,
                'latitude'           => $validated['latitude'] ?? null,
                'longitude'          => $validated['longitude'] ?? null,
                'directions'         => $validated['directions'] ?? null,
                'verification_notes' => $validated['verification_notes'] ?? null,
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
                'actor_id'   => $user->id,
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

        return response()->json(['success' => true]);
    }

    public function requestVerification(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        abort_unless(
            $listing->assigned_to === $user->id
                && $listing->queue_status === QueueStatus::assigned()->value,
            404,
        );

        $existingIds = $listing->images()->pluck('id')->all();

        $validated = $request->validate([
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
            'verification_notes'   => ['nullable', 'string', 'max:2000'],
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
            'title.required'           => 'Title is required.',
            'title.max'                => 'Title is too long (max 200 characters).',
            'directions.required'      => 'Directions are required.',
            'directions.max'           => 'Directions are too long (max 500 characters).',
            'verification_notes.max'   => 'Verification notes must be 2000 characters or fewer.',
            'latitude.required'        => 'Drop a map pin before requesting verification.',
            'latitude.between'         => 'Latitude must be between -90 and 90.',
            'longitude.required'       => 'Drop a map pin before requesting verification.',
            'longitude.between'        => 'Longitude must be between -180 and 180.',
            'photos.required'          => 'Upload at least one new photo from your visit.',
            'photos.min'               => 'Upload at least one new photo from your visit.',
            'photos.max'               => 'Maximum 20 photos allowed.',
            'photos.*.key.starts_with' => 'Invalid photo key.',
            'listing_type.required'    => 'Listing type is required.',
            'listing_type.in'          => 'Invalid listing type.',
            'price_monthly.min'        => 'Monthly rent must be at least ₱1.',
            'barangay.required'        => 'Barangay is required.',
            'barangay.in'              => 'Invalid barangay.',
            'sqm.min'                  => 'Floor area must be at least 1 sqm.',
            'amenities.*.exists'       => "One or more selected amenities don't exist.",
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

        DB::transaction(function () use ($request, $user, $listing, $validated, $photos, $removedImageIds) {
            $listing->update([
                'title'              => $validated['title'],
                'description'        => $validated['description'] ?? null,
                'type'               => $validated['listing_type'] ?? null,
                'price_monthly'      => $validated['price_monthly'] ?? null,
                'barangay'           => $validated['barangay'] ?? null,
                'beds'               => $validated['beds'] ?? null,
                'baths'              => $validated['baths'] ?? null,
                'sqm'                => $validated['sqm'] ?? null,
                'latitude'           => $validated['latitude'],
                'longitude'          => $validated['longitude'],
                'directions'         => $validated['directions'],
                'verification_notes' => $validated['verification_notes'] ?? null,
                'queue_status'       => QueueStatus::visited()->value,
                'visited_at'         => Carbon::now(),
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
                'actor_id'   => $user->id,
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

        return response()->json(['success' => true]);
    }

    public function priorityToggle(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        abort_unless(
            $listing->assigned_to === $user->id
                && $listing->queue_status === QueueStatus::assigned()->value,
            404,
        );

        $newState = ! $listing->is_field_priority;

        $updates = ['is_field_priority' => $newState];
        if (! $newState) {
            $updates['field_priority_order'] = null;
        }

        $listing->update($updates);

        return response()->json(['success' => true]);
    }

    /**
     * @param  class-string<\Spatie\Enum\Enum>  $enum
     * @return array<int, array{value: string, label: string}>
     */
    private function enumCatalog(string $enum): array
    {
        return collect($enum::toValues())
            ->map(fn ($v) => ['value' => $v, 'label' => $enum::from($v)->label])
            ->all();
    }
}
