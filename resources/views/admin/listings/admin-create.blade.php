@extends('layouts.admin')

@section('title', 'Admin Add Listing — RentConnectPH')
@section('page', 'admin-add-listing')

@push('scripts')
@php
    $authed = auth('admin')->user();
    $initials = collect(explode(' ', $authed->name))
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: {
            name:       @json($authed->name),
            initials:   @json($initials),
            role_label: 'Super Admin',
        },
    };

    window.__INITIAL_ADD_LISTING__ = {
        listingTypes: @json($listingTypes),
        barangays:    @json($barangays),
        amenities:    @json($amenities),
    };
</script>
@endpush
