<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'listings' => $this->mockListings(),
        ]);
    }

    private function mockListings(): array
    {
        $img = fn (string $label) => 'https://placehold.co/600x400/e5e7eb/6b7280?text=' . rawurlencode($label);

        return [
            ['id' => 'v-1', 'title' => 'The Garden Suites',      'type' => 'Apartment', 'price_monthly' => 12500, 'beds' => 2, 'baths' => 1, 'sqft' => 45,  'barangay' => 'Kauswagan',     'image' => $img('Garden Suites'),    'section' => 'verified'],
            ['id' => 'v-2', 'title' => 'Pueblo Loft 12B',        'type' => 'Studio',    'price_monthly' => 18000, 'beds' => 1, 'baths' => 1, 'sqft' => 32,  'barangay' => 'Pueblo de Oro', 'image' => $img('Pueblo Loft'),      'section' => 'verified'],
            ['id' => 'v-3', 'title' => 'Uptown Family House',    'type' => 'House',     'price_monthly' => 35000, 'beds' => 4, 'baths' => 3, 'sqft' => 180, 'barangay' => 'Uptown',        'image' => $img('Uptown House'),     'section' => 'verified'],
            ['id' => 'r-1', 'title' => 'Carmen Tower 8F',        'type' => 'Condo',     'price_monthly' => 22000, 'beds' => 2, 'baths' => 2, 'sqft' => 60,  'barangay' => 'Carmen',        'image' => $img('Carmen Tower'),     'section' => 'recently'],
            ['id' => 'r-2', 'title' => 'Nazareth Bedspacer',     'type' => 'Bedspacer', 'price_monthly' => 4500,  'beds' => 1, 'baths' => 1, 'sqft' => 12,  'barangay' => 'Nazareth',      'image' => $img('Nazareth Bed'),     'section' => 'recently'],
            ['id' => 'r-3', 'title' => 'Lapasan Compact Studio', 'type' => 'Studio',    'price_monthly' => 9800,  'beds' => 1, 'baths' => 1, 'sqft' => 24,  'barangay' => 'Lapasan',       'image' => $img('Lapasan Studio'),   'section' => 'recently'],
            ['id' => 'r-4', 'title' => 'Indahag Hillside Home',  'type' => 'House',     'price_monthly' => 28000, 'beds' => 3, 'baths' => 2, 'sqft' => 140, 'barangay' => 'Indahag',       'image' => $img('Indahag Home'),     'section' => 'recently'],
            ['id' => 'r-5', 'title' => 'Macasandig 2-BR Flat',   'type' => 'Apartment', 'price_monthly' => 16500, 'beds' => 2, 'baths' => 1, 'sqft' => 55,  'barangay' => 'Macasandig',    'image' => $img('Macasandig Flat'),  'section' => 'recently'],
        ];
    }
}
