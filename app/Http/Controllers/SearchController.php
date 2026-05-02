<?php

namespace App\Http\Controllers;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Resources\SearchResource;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const LEGACY_BUDGET_RANGES = [
        'lt10k'  => [null,  9999],
        '10-20k' => [10000, 20000],
        '20-30k' => [20000, 30000],
        'gt30k'  => [30001, null],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->maybeRedirectLegacyBudget($request)) {
            return $redirect;
        }

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

        $payload = (new SearchResource($listings))->resolve();
        $payload['filters'] = [
            'q'          => $q,
            'budget_min' => $min,
            'budget_max' => $max,
            'area'       => $area,
            'type'       => $type,
        ];

        return view('search', ['search' => $payload]);
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

    private function maybeRedirectLegacyBudget(Request $request): ?RedirectResponse
    {
        $legacy = $request->query('budget');
        if (!is_string($legacy)) return null;
        if (!array_key_exists($legacy, self::LEGACY_BUDGET_RANGES)) return null;

        $hasNew = $request->query('budget_min') !== null
              || $request->query('budget_max') !== null;

        [$min, $max] = self::LEGACY_BUDGET_RANGES[$legacy];

        $next = $request->query();
        unset($next['budget']);
        if (!$hasNew) {
            if ($min !== null) $next['budget_min'] = (string) $min;
            if ($max !== null) $next['budget_max'] = (string) $max;
        }

        return redirect()->to(route('search', $next), 301);
    }

    private function validBarangay(?string $value): ?string
    {
        return in_array($value, Barangay::toValues(), true) ? $value : null;
    }

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
}
