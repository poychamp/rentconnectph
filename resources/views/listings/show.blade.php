@extends('layouts.app')

@section('title', $listing['title'] . ' — RentConnectPH')
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
