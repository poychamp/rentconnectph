@extends('layouts.admin')

@section('title', 'Leads — RentConnectPH Admin')
@section('page', 'admin-leads')

@push('scripts')
@php
    $authUser = (new \App\Http\Resources\AdminAuthUserResource(auth('admin')->user()))->resolve();
@endphp
<script>
    window.__INITIAL_LEADS__ = {
        user:    @json($authUser),
        leads:   @json($leads),
        filters: @json($filters),
        errors:  @json($errors->getBags() ? collect($errors->getBags())->mapWithKeys(fn ($bag, $key) => [$key => $bag->getMessages()])->toArray() : null),
        oldInput: @json(old() ?: null),
    };
</script>
@endpush
