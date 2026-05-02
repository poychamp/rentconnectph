@extends('layouts.app')

@section('title', 'About — RentConnectPH')
@section('page', 'about')

@push('scripts')
<script>
    window.__INITIAL_ABOUT__ = @json([
        'brokerImage' => asset('img/broker.webp'),
    ]);
</script>
@endpush
