@extends('layouts.admin')

@section('title', 'Unverified Listings — RentConnectPH Admin')
@section('page', 'admin-unverified-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_UNVERIFIED__ = @json($unverified);
</script>
@endpush
