@extends('layouts.field')

@section('title', 'Priority Listings — RentConnectPH Field')
@section('page', 'field-priority')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_FIELD_PRIORITY__ = {
        user:     @json($authUser),
        priority: @json($priority),
    };
</script>
@endpush
