@extends('layouts.field')

@section('title', 'Verified — RentConnectPH Field')
@section('page', 'field-verified-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_FIELD_VERIFIED_LISTINGS__ = {
        user:        @json($authUser),
        verified:    @json($verified),
        q:           @json($q),
        isSearching: @json($isSearching),
    };
</script>
@endpush
