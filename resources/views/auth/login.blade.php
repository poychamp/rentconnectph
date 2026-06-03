@extends('layouts.admin')

@section('title', 'Sign in — RentConnectPH')
@section('page', 'auth-login')

@push('scripts')
<script>
    window.__INITIAL_LOGIN__ = {
        oldEmail:   @json(old('email')),
        loginError: @json(session('login_error') ?? $errors->first()),
    };
</script>
@endpush
