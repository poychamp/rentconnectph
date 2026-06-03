@extends('layouts.app')

@section('title', 'Forgot password — RentConnectPH')
@section('page', 'forgot-password')

@push('scripts')
<script>
    window.__INITIAL_FORGOT_PASSWORD__ = {
        errors:   @json($errors->getMessages() ?: null),
        oldInput: @json(old() ?: null),
        success:  @json(session('success')),
    };
</script>
@endpush
