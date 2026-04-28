<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

// Public — login is the entry point. Bounce authed admins to dashboard
// (no point letting them see the login form).
Route::middleware('guest.admin')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Admin\AuthController::class, 'login'])->name('login.attempt');
});

// Guarded — auth:admin required for everything below
Route::middleware('auth:admin')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::get('listings/create', [Admin\ListingController::class, 'create'])
        ->name('listings.create');
    Route::post('listings', [Admin\ListingController::class, 'store'])
        ->name('listings.store');

    Route::get('listings/{listing:uuid}/edit', [Admin\ListingController::class, 'edit'])
        ->name('listings.edit');
    Route::put('listings/{listing:uuid}', [Admin\ListingController::class, 'update'])
        ->name('listings.update');
    Route::put('listings/{listing:uuid}/deactivate', [Admin\ListingController::class, 'deactivate'])
        ->name('listings.deactivate');
    Route::get('listings/{listing:uuid}/restore', [Admin\ListingController::class, 'showRestore'])
        ->withTrashed()
        ->name('listings.restore.show');
    Route::put('listings/{listing:uuid}/restore', [Admin\ListingController::class, 'restore'])
        ->withTrashed()
        ->name('listings.restore');
    Route::put('listings/{listing:uuid}/reject', [Admin\ListingController::class, 'reject'])
        ->name('listings.reject');

    Route::get('verified-listings', [Admin\ListingController::class, 'verifiedIndex'])
        ->name('verified-listings.index');

    Route::get('unverified-listings', [Admin\ListingController::class, 'unverifiedIndex'])
        ->name('unverified-listings.index');

    Route::get('deactivated-listings', [Admin\ListingController::class, 'deactivatedIndex'])
        ->name('deactivated-listings.index');

    Route::get('rejected-listings', [Admin\ListingController::class, 'rejectedIndex'])
        ->name('rejected-listings.index');

    Route::get('listings/{listing:uuid}/reopen', [Admin\ListingController::class, 'showReopen'])
        ->withTrashed()
        ->name('listings.reopen.show');
    Route::put('listings/{listing:uuid}/reopen', [Admin\ListingController::class, 'reopen'])
        ->withTrashed()
        ->name('listings.reopen');

    // Vapor's signed S3 URL endpoint — browser calls this to get a pre-signed URL,
    // then PUTs the file directly to S3. Gated to admins so non-admins can't generate URLs.
    Route::post('vapor/signed-storage-url', [\Laravel\Vapor\Http\Controllers\SignedStorageUrlController::class, 'store'])
        ->name('vapor.signed-storage-url');
});
