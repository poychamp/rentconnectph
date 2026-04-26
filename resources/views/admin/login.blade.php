@extends('layouts.admin')

@section('title', 'Admin Login — RentConnectPH')
@section('page', 'admin-login')

@push('scripts')
<script>
    window.__INITIAL_LOGIN__ = {
        oldEmail:   @json(old('email')),
        loginError: @json(session('login_error') ?? $errors->first()),
    };
</script>
@endpush
