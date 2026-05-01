@extends('layouts.field')

@section('title', 'Dashboard — RentConnectPH Field')
@section('page', 'field-dashboard')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_FIELD_DASHBOARD__ = {
        user: @json($authUser),
    };
</script>
@endpush
