<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingCardResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q   = trim((string) $request->query('q', ''));
        $min = $this->validBudgetBound($request->query('budget_min'));
        $max = $this->validBudgetBound($request->query('budget_max'));
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $area = $this->validBarangay($request->query('area'));
        $type = $this->validListingTypes($request->query('type'));

        if ($q !== '') {
            $query = Listing::search($q)
                ->where('is_verified', true)
                ->query(fn ($q) => $q->with('displayImage')->withCount('images'));

            if (!empty($type)) $query->whereIn('type', $type);
            if ($area)         $query->where('barangay', $area);
            if ($min !== null) $query->where('price_monthly', '>=', $min);
            if ($max !== null) $query->where('price_monthly', '<=', $max);

            $listings = $query->paginate(24);
        } else {
            $query = Listing::with('displayImage')
                ->withCount('images')
                ->verified()
                ->orderByDesc('listed_at');

            if (!empty($type)) $query->whereIn('type', $type);
            if ($area)         $query->where('barangay', $area);
            if ($min !== null) $query->where('price_monthly', '>=', $min);
            if ($max !== null) $query->where('price_monthly', '<=', $max);

            $listings = $query->paginate(24);
        }

        $listings->appends($request->only(['q', 'budget_min', 'budget_max', 'area', 'type']));

        return ListingCardResource::collection($listings)
            ->additional([
                'filters' => [
                    'q'          => $q,
                    'budget_min' => $min,
                    'budget_max' => $max,
                    'area'       => $area,
                    'type'       => $type,
                ],
                'catalogs' => [
                    'listing_types' => $this->enumCatalog(ListingType::class),
                    'barangays'     => $this->enumCatalog(Barangay::class),
                ],
            ])
            ->response();
    }

    private function validBudgetBound(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (!ctype_digit(trim($value))) {
            return null;
        }
        return (int) $value;
    }

    private function validBarangay(?string $value): ?string
    {
        return in_array($value, Barangay::toValues(), true) ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private function validListingTypes(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $allowed = ListingType::toValues();

        return collect(explode(',', $csv))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '' && in_array($v, $allowed, true))
            ->unique()
            ->values()
            ->all();
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
