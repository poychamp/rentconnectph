@extends('layouts.admin')

@section('title', 'Amenities — RentConnectPH Admin')
@section('page', 'admin-amenities')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_ADMIN_AMENITIES__ = {
        user:      @json($authUser),
        amenities: @json($amenities),
        counts:    @json($counts),
    };
</script>
@endpush
