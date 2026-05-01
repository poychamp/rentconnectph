@extends('layouts.admin')

@section('title', 'Deactivated Listings — RentConnectPH Admin')
@section('page', 'admin-deactivated-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_DEACTIVATED__ = @json($deactivated);
</script>
@endpush
