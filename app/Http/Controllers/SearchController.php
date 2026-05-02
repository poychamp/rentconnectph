<?php

namespace App\Http\Controllers;

use App\Enums\Barangay;
use App\Enums\ListingType;
use App\Http\Resources\SearchResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const BUDGETS = ['lt10k', '10-20k', '20-30k', 'gt30k'];

    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $budget = $this->validBudget($request->query('budget'));
        $area   = $this->validBarangay($request->query('area'));
        $type   = $this->validListingTypes($request->query('type'));

        if ($q !== '') {
            $query = Listing::search($q)
                ->where('is_verified', true)
                ->query(fn ($q) => $q->with('displayImage')->withCount('images'));

            if (!empty($type)) $query->whereIn('type', $type);
            if ($area)         $query->where('barangay', $area);
            if ($budget)       $this->applyBudgetScout($query, $budget);

            $listings = $query->paginate(24);
        } else {
            $query = Listing::with('displayImage')
                ->withCount('images')
                ->verified()
                ->orderByDesc('listed_at');
            if (!empty($type)) $query->whereIn('type', $type);
            if ($area)         $query->where('barangay', $area);
            if ($budget)       $this->applyBudgetEloquent($query, $budget);

            $listings = $query->paginate(24);
        }

        $listings->appends($request->only(['q', 'budget', 'area', 'type']));

        $payload = (new SearchResource($listings))->resolve();
        $payload['filters'] = compact('q', 'budget', 'area', 'type');

        return view('search', ['search' => $payload]);
    }

    private function validBudget(?string $value): ?string
    {
        return in_array($value, self::BUDGETS, true) ? $value : null;
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

    private function applyBudgetEloquent($query, string $budget): void
    {
        match ($budget) {
            'lt10k'  => $query->where('price_monthly', '<', 10000),
            '10-20k' => $query->whereBetween('price_monthly', [10000, 20000]),
            '20-30k' => $query->whereBetween('price_monthly', [20000, 30000]),
            'gt30k'  => $query->where('price_monthly', '>', 30000),
        };
    }

    private function applyBudgetScout($query, string $budget): void
    {
        match ($budget) {
            'lt10k'  => $query->where('price_monthly', '<', 10000),
            '10-20k' => $query->where('price_monthly', '>=', 10000)->where('price_monthly', '<=', 20000),
            '20-30k' => $query->where('price_monthly', '>=', 20000)->where('price_monthly', '<=', 30000),
            'gt30k'  => $query->where('price_monthly', '>', 30000),
        };
    }
}
