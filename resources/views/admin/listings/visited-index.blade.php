@extends('layouts.admin')

@section('title', 'Visited Listings — RentConnectPH Admin')
@section('page', 'admin-visited-listings')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_VISITED_LISTINGS__ = {
        user:    @json($authUser),
        visited: @json($visited),
    };
</script>
@endpush
