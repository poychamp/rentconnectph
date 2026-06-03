@extends('layouts.field')

@section('title', 'Submitted — RentConnectPH Field')
@section('page', 'field-submitted-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_FIELD_SUBMITTED_LISTINGS__ = {
        user:        @json($authUser),
        submitted:   @json($submitted),
        q:           @json($q),
        isSearching: @json($isSearching),
    };
</script>
@endpush
