@extends('layouts.app')

@section('title', 'Contact — RentConnectPH')
@section('page', 'contact')

@push('scripts')
<script>
    window.__INITIAL_CONTACT__ = {
        errors:   @json($errors->getMessages() ?: null),
        oldInput: @json(old() ?: null),
    };
</script>
@endpush
