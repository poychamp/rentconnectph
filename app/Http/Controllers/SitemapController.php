<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $cacheKey = 'sitemap.xml.' . app()->environment();

        $xml = Cache::remember($cacheKey, Carbon::now()->addDay(), function () {
            $listings = Listing::verified()
                ->select(['uuid', 'updated_at'])
                ->get();

            $now = Carbon::now()->toAtomString();

            return view('sitemap', [
                'staticUrls' => [
                    ['loc' => url('/') . '/',  'lastmod' => $now],
                    ['loc' => url('/search'),  'lastmod' => $now],
                    ['loc' => url('/about'),   'lastmod' => $now],
                    ['loc' => url('/contact'), 'lastmod' => $now],
                    ['loc' => url('/privacy'), 'lastmod' => $now],
                    ['loc' => url('/terms'),   'lastmod' => $now],
                ],
                'listings' => $listings,
            ])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
