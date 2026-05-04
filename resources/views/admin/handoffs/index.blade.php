@extends('layouts.admin')

@section('title', 'Handoffs — RentConnectPH Admin')
@section('page', 'admin-handoffs')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_HANDOFFS__ = {
        user:     @json($authUser),
        handoffs: @json($handoffs),
    };
</script>
@endpush
