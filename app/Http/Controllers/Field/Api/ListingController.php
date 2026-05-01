<?php

namespace App\Http\Controllers\Field\Api;

use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function priorityToggle(Request $request, Listing $listing): JsonResponse
    {
        $userId = auth('admin')->id();

        if ($listing->assigned_to !== $userId
            || $listing->queue_status !== QueueStatus::assigned()->value) {
            throw ValidationException::withMessages([
                'listing' => 'This listing is no longer assigned to you.',
            ]);
        }

        $newState = ! $listing->is_field_priority;

        $updates = ['is_field_priority' => $newState];
        if (! $newState) {
            // Toggle-off clears the order — stale ordering from a prior priority
            // cycle is meaningless once the listing leaves the priority pool.
            $updates['field_priority_order'] = null;
        }

        $listing->update($updates);

        return response()->json([
            'ok'                => true,
            'is_field_priority' => $newState,
            'message'           => $newState
                ? 'Added to priority queue.'
                : 'Removed from priority queue.',
        ]);
    }
}
