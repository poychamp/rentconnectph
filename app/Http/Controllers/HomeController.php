<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResource;
use App\Models\Listing;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Listing::with('displayImage')
            ->withCount('images')
            ->featured()
            ->verified()
            ->orderBy('featured_order')
            ->orderBy('id')
            ->get();

        $recent = Listing::with('displayImage')
            ->withCount('images')
            ->recentlyListed()
            ->limit(6)
            ->get();

        $home = (new HomeResource(['featured' => $featured, 'recently' => $recent]))->resolve();

        return view('home', ['home' => $home]);
    }
}
