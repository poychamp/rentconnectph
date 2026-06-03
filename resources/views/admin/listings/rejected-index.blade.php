@extends('layouts.admin')

@section('title', 'Rejected Listings — RentConnectPH Admin')
@section('page', 'admin-rejected-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };

    window.__INITIAL_REJECTED__ = @json($rejected);
</script>
@endpush
