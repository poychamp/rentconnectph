@extends('layouts.admin')

@section('title', 'Featured Listings — RentConnectPH Admin')
@section('page', 'admin-featured-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_FEATURED__ = @json($featured);
</script>
@endpush
