@extends('layouts.field')

@section('title', 'Listings — RentConnectPH Field')
@section('page', 'field-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_FIELD_LISTINGS__ = {
        user:        @json($authUser),
        listings:    @json($listings),
        q:           @json($q),
        isSearching: @json($isSearching),
    };
</script>
@endpush
