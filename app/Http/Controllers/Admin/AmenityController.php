<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAmenityResource;
use App\Models\Amenity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AmenityController extends Controller
{
    public function index(): View
    {
        // Single load, ordered by (sort_order ASC, id ASC). Self-heal walk:
        // null OR duplicate sort_order rows get appended past max(sort_order)
        // via in-memory bookkeeping. Tie-break by id ASC: lower-id duplicate
        // keeps its value; higher-id collisions get bumped. Idempotent —
        // clean state writes nothing. Mirrors Admin\ListingController::featuredIndex.
        $rows = Amenity::orderBy('sort_order')->orderBy('id')->get();

        $seen = [];
        $kept = collect();
        $irregular = collect();
        foreach ($rows as $row) {
            if ($row->sort_order === null || isset($seen[$row->sort_order])) {
                $irregular->push($row);
            } else {
                $seen[$row->sort_order] = true;
                $kept->push($row);
            }
        }

        if ($irregular->isNotEmpty()) {
            $maxOrder = empty($seen) ? 0 : max(array_keys($seen));
            DB::transaction(function () use ($irregular, $maxOrder) {
                foreach ($irregular as $i => $amenity) {
                    $amenity->update(['sort_order' => $maxOrder + 1 + $i]);
                }
            });
            $rows = $kept->concat($irregular);
        }

        return view('admin.amenities.index', [
            'amenities' => AdminAmenityResource::collection($rows)->resolve(),
            'counts'    => [
                'active'  => Amenity::count(),
                'deleted' => Amenity::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.amenities.create');
    }

    public function edit(Amenity $amenity): View
    {
        return view('admin.amenities.edit', [
            'amenity' => (new AdminAmenityResource($amenity))->resolve(),
        ]);
    }

    public function update(Request $request, Amenity $amenity): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('amenities', 'slug')->ignore($amenity->id)->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'Name is required.',
            'name.max'      => 'Name is too long (max 80 characters).',
            'slug.required' => 'Slug is required.',
            'slug.max'      => 'Slug is too long (max 32 characters).',
            'slug.regex'    => 'Slug must be snake_case (lowercase letters, digits, underscores; starts with a letter).',
            'slug.unique'   => 'Slug already exists.',
        ]);

        // Mirrors store(): icon stays in lockstep with slug. sort_order is owned
        // by the drag-reorder AJAX endpoint; id/uuid/timestamps are immutable.
        // Only the editable triple flows into update() — read-only fields in the
        // request payload silent-ignore.
        $amenity->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'icon' => $validated['slug'],
        ]);

        return redirect()
            ->route('admin.amenities.index')
            ->with('success', "Amenity '{$amenity->name}' updated.");
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('amenities', 'slug')->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'Name is required.',
            'name.max'      => 'Name is too long (max 80 characters).',
            'slug.required' => 'Slug is required.',
            'slug.max'      => 'Slug is too long (max 32 characters).',
            'slug.regex'    => 'Slug must be snake_case (lowercase letters, digits, underscores; starts with a letter).',
            'slug.unique'   => 'Slug already exists.',
        ]);

        // Server-derived: icon mirrors slug. Vue iconPaths maps key off slug
        // (which equals icon), so adding a new amenity needs only an
        // iconPaths entry keyed by the slug to render its glyph.
        $validated['icon'] = $validated['slug'];

        $amenity = Amenity::create($validated);

        return redirect()
            ->route('admin.amenities.index')
            ->with('success', "Amenity '{$amenity->name}' created.");
    }

    public function deletedIndex(): View
    {
        $rows = Amenity::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.amenities.deleted', [
            'amenities' => AdminAmenityResource::collection($rows)->resolve(),
            'counts'    => [
                'active'  => Amenity::count(),
                'deleted' => Amenity::onlyTrashed()->count(),
            ],
        ]);
    }

    public function restore(Amenity $amenity): RedirectResponse
    {
        // Trashed-only. Route binding uses ->withTrashed() to FIND the row;
        // controller bounces active UUIDs (nothing to restore).
        abort_unless($amenity->trashed(), 404);

        $amenity->restore();

        // sort_order stays null — self-heal-on-read at the active index appends
        // past max_active + 1, landing the restored row at the end of the list.
        // No explicit sort_order assignment here.

        return redirect()
            ->route('admin.deleted-amenities.index')
            ->with('success', "Amenity '{$amenity->name}' restored.");
    }

    public function destroy(Amenity $amenity): RedirectResponse
    {
        // Soft-delete only — SoftDeletes trait sets deleted_at, doesn't hard-delete.
        // Pivot rows (amenity_listing) survive: cascade fires on hard-delete only.
        // Restore later reattaches listings automatically since the pivot is intact.
        //
        // Clear sort_order before deleting so a future restore lands at the END
        // of the active list (self-heal-on-read appends null sort_order rows
        // past max_active + 1). Prevents restored amenities from inserting back
        // into the middle and disrupting the admin's current order.
        $name = $amenity->name;
        $amenity->update(['sort_order' => null]);
        $amenity->delete();

        return redirect()
            ->route('admin.amenities.index')
            ->with('success', "Amenity '{$name}' deleted.");
    }
}
