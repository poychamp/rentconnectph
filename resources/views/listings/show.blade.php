@extends('layouts.app')

@php
    $type      = $listing['type_label']   ?? null;
    $barangay  = $listing['barangay_label'] ?? null;
    $price     = $listing['price_monthly'] ?? null;
    $priceFmt  = $price !== null ? '₱' . number_format($price) . '/month' : null;

    $specs = [];
    if (($listing['beds']  ?? null) !== null) $specs[] = $listing['beds']  . ' ' . \Illuminate\Support\Str::plural('bed',  $listing['beds']);
    if (($listing['baths'] ?? null) !== null) $specs[] = $listing['baths'] . ' ' . \Illuminate\Support\Str::plural('bath', $listing['baths']);
    if (($listing['sqm']   ?? null) !== null) $specs[] = $listing['sqm']   . ' sqm';

    $where = $barangay ? $barangay . ', Cagayan de Oro' : 'Cagayan de Oro';
    $lede  = ($type ? $type . ' for rent in ' : 'For rent in ') . $where;
    if ($priceFmt) $lede .= ' — ' . $priceFmt;

    $listingDescription = $lede . '.' . ($specs ? ' ' . implode(', ', $specs) . '.' : '') . ' View photos and amenities on RentConnectPH.';

    $ogTitleParts = [$listing['title']];
    if ($priceFmt) $ogTitleParts[] = $priceFmt;
    $ogTitleSuffix = $barangay ? ' in ' . $barangay : '';
    $listingOgTitle = implode(' — ', $ogTitleParts) . $ogTitleSuffix;

    $inquireOldInput = old() ? [
        'listing_uuid' => old('listing_uuid'),
        'name'         => old('name'),
        'phone'        => old('phone'),
    ] : null;

    $inquireData = [
        'oldInput'         => $inquireOldInput,
        'errors'           => $errors->getBag('default')->toArray() ?: null,
        'openInquireModal' => old('listing_uuid') !== null,
    ];
@endphp

@section('title', $listing['title'] . ' — RentConnectPH')
@section('description', $listingDescription)
@section('og_url', url('/listings/' . $listing['uuid']))
@section('og_title', $listingOgTitle)
@section('og_description', $listingDescription)
@section('og_image', $listing['images'][0]['url'] ?? asset('og/brand.png'))
@section('page', 'listing-detail')

@push('scripts')
<script>
    window.__INITIAL_LISTING__ = @json($listing);
    window.__INITIAL_INQUIRE__ = @json($inquireData);
</script>
@endpush
