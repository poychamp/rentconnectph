@extends('layouts.admin')

@section('title', 'Deleted Amenities — RentConnectPH Admin')
@section('page', 'admin-deleted-amenities')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_ADMIN_DELETED_AMENITIES__ = {
        user:      @json($authUser),
        amenities: @json($amenities),
        counts:    @json($counts),
    };
</script>
@endpush
