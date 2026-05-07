<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Enums\AppPermission;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FieldListingDetailResource;
use App\Http\Resources\FieldListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'listing' => (new FieldListingDetailResource($listing))->resolve(),
        ]);
    }
}
