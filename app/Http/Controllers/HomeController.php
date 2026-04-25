<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResource;
use App\Models\Listing;

class HomeController extends Controller
{
    public function index()
    {
        $verified = Listing::with('displayImage')
            ->featured()
            ->verified()
            ->limit(3)
            ->get();

        $recent = Listing::with('displayImage')
            ->recentlyVerified()
            ->where('is_featured', false)
            ->limit(5)
            ->get();

        $home = (new HomeResource(['verified' => $verified, 'recently' => $recent]))->resolve();

        return view('home', ['home' => $home]);
    }
}
