@extends('layouts.app')

@section('title', $listing['title'] . ' — RentConnectPH')
@section('description', $listing['type_label'] . ' for rent in ' . $listing['barangay_label'] . ', Cagayan de Oro — ₱' . number_format($listing['price_monthly']) . '/month. ' . $listing['beds'] . ' ' . \Illuminate\Support\Str::plural('bed', $listing['beds']) . ', ' . $listing['baths'] . ' ' . \Illuminate\Support\Str::plural('bath', $listing['baths']) . ', ' . $listing['sqm'] . ' sqm. View photos and amenities on RentConnectPH.')
@section('page', 'listing-detail')

@php
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

@push('scripts')
<script>
    window.__INITIAL_LISTING__ = @json($listing);
    window.__INITIAL_INQUIRE__ = @json($inquireData);
</script>
@endpush
