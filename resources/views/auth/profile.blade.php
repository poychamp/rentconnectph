@extends('layouts.admin')

@section('title', 'Profile — RentConnectPH')
@section('page', 'auth-profile')

@push('scripts')
@php
    $authProfileInitial = [
        'name' => $name,
        'roleLabel' => $roleLabel,
        'dashboardUrl' => $dashboardUrl,
        'errorsUpdateName' => $errors->getBag('update-name')->toArray() ?: null,
        'errorsUpdatePassword' => $errors->getBag('update-password')->toArray() ?: null,
        'oldInput' => [
            'name' => old('name'),
        ],
    ];
@endphp
<script>
    window.__INITIAL_AUTH_PROFILE__ = @json($authProfileInitial);
</script>
@endpush
