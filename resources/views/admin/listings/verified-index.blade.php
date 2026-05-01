@extends('layouts.admin')

@section('title', 'Verified Listings — RentConnectPH Admin')
@section('page', 'admin-verified-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_VERIFIED__ = @json($verified);
</script>
@endpush
