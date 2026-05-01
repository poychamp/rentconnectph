@extends('layouts.admin')

@section('title', 'Dashboard — RentConnectPH Admin')
@section('page', 'admin-dashboard')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_DASHBOARD__ = {
        user: @json($authUser),
    };
</script>
@endpush
