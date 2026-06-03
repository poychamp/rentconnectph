@extends('layouts.app')

@section('title', 'Contact — RentConnectPH')
@section('description', 'Send a message to the RentConnectPH team about listings, partnerships, or platform issues. We respond within one business day.')
@section('og_title', 'Contact RentConnectPH')
@section('og_description', 'Send a message to the RentConnectPH team about listings, partnerships, or platform issues. We respond within one business day.')
@section('og_image', asset('og/contact.png'))
@section('page', 'contact')

@push('scripts')
<script>
    window.__INITIAL_CONTACT__ = {
        errors:   @json($errors->getMessages() ?: null),
        oldInput: @json(old() ?: null),
    };
</script>
@endpush
