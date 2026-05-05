@extends('layouts.app')

@section('title', 'Reset password — RentConnectPH')
@section('page', 'reset-password')

@push('scripts')
<script>
    window.__INITIAL_RESET_PASSWORD__ = {
        errors:   @json($errors->getMessages() ?: null),
        oldInput: @json(old() ?: null),
        token:    @json($token),
        email:    @json($email),
    };
</script>
@endpush
