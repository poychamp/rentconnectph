@extends('layouts.app')

@section('title', 'Privacy Policy — RentConnectPH')
@section('description', 'How RentConnectPH collects, uses, shares, and protects your personal information under the Philippines Data Privacy Act of 2012.')
@section('og_title', 'Privacy Policy — RentConnectPH')
@section('og_description', 'How RentConnectPH collects, uses, shares, and protects your personal information under the Philippines Data Privacy Act of 2012.')
@section('og_image', asset('og/brand.png'))
@section('page', 'privacy')

@push('scripts')
<script>
    window.__INITIAL_PRIVACY__ = @json([
        'effectiveDate' => '2026-05-14',
    ]);
</script>
@endpush
