<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResource;
use App\Models\Listing;

class HomeController extends Controller
{
    public function index()
    {
        $verified = Listing::with('displayImage')
            ->withCount('images')
            ->featured()
            ->verified()
            ->get();

        $recent = Listing::with('displayImage')
            ->withCount('images')
            ->recentlyVerified()
            ->limit(6)
            ->get();

        $home = (new HomeResource(['verified' => $verified, 'recently' => $recent]))->resolve();

        return view('home', ['home' => $home]);
    }
}
