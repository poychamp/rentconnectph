<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmenityController extends Controller
{
    public function updateSort(Request $request): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ]);

        $order = $request->input('order');

        // Slice: only non-trashed amenities. Soft-deleted rows aren't sortable
        // from the active index — they live behind /admin/deleted-amenities.
        $amenities = Amenity::whereIn('uuid', $order)
            ->get()
            ->keyBy('uuid');

        if ($amenities->count() !== count($order)) {
            throw ValidationException::withMessages([
                'order' => 'One or more amenities are not in the active slice.',
            ]);
        }

        DB::transaction(function () use ($amenities, $order) {
            foreach ($order as $i => $uuid) {
                $amenities[$uuid]->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
