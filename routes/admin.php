<?php

use App\Enums\AppPermission;
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
    Route::get('/', [Admin\DashboardController::class, 'index'])
        ->middleware('can:' . AppPermission::adminAccess()->value)
        ->name('dashboard');
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    // Desk-team listings scope — gated by `listings.manage`. Field officers
    // (role=field) get 403 here; their own surface lands behind
    // `listings.field-work` when the field-side UI ships.
    Route::middleware('can:' . AppPermission::listingsManage()->value)->group(function () {
        Route::get('listings/create', [Admin\ListingController::class, 'create'])
            ->name('listings.create');
        Route::post('listings', [Admin\ListingController::class, 'store'])
            ->name('listings.store');

        Route::get('listings/{listing:uuid}/edit', [Admin\ListingController::class, 'edit'])
            ->name('listings.edit');
        Route::get('listings/{listing:uuid}/unverified-edit', [Admin\ListingController::class, 'unverifiedEdit'])
            ->name('listings.unverified-edit');
        Route::get('listings/{listing:uuid}/verify-edit', [Admin\ListingController::class, 'verifyEdit'])
            ->name('listings.verify-edit');
        Route::put('listings/{listing:uuid}/verify', [Admin\ListingController::class, 'verify'])
            ->name('listings.verify');
        Route::put('listings/{listing:uuid}', [Admin\ListingController::class, 'update'])
            ->name('listings.update');
        Route::put('listings/{listing:uuid}/unverified-update', [Admin\ListingController::class, 'unverifiedUpdate'])
            ->name('listings.unverified-update');
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

        Route::get('visited-listings', [Admin\ListingController::class, 'visitedIndex'])
            ->name('visited-listings.index');

        Route::get('deactivated-listings', [Admin\ListingController::class, 'deactivatedIndex'])
            ->name('deactivated-listings.index');

        Route::get('rejected-listings', [Admin\ListingController::class, 'rejectedIndex'])
            ->name('rejected-listings.index');

        Route::get('featured-listings', [Admin\ListingController::class, 'featuredIndex'])
            ->name('featured-listings.index');

        Route::put('api/listings/featured-sort', [Admin\Api\ListingController::class, 'updateFeaturedOrder'])
            ->name('api.listings.featured-sort');

        Route::get('listings/{listing:uuid}/reopen', [Admin\ListingController::class, 'showReopen'])
            ->withTrashed()
            ->name('listings.reopen.show');
        Route::put('listings/{listing:uuid}/reopen', [Admin\ListingController::class, 'reopen'])
            ->withTrashed()
            ->name('listings.reopen');
    });

    // Amenity catalog management — gated by `amenities.manage`. Super-admin only
    // via Gate::before bypass; permission exists in DB but no explicit role grant.
    Route::middleware('can:' . AppPermission::amenitiesManage()->value)->group(function () {
        Route::get('amenities', [Admin\AmenityController::class, 'index'])
            ->name('amenities.index');

        Route::get('amenities/create', [Admin\AmenityController::class, 'create'])
            ->name('amenities.create');

        Route::post('amenities', [Admin\AmenityController::class, 'store'])
            ->name('amenities.store');

        Route::get('amenities/{amenity:uuid}/edit', [Admin\AmenityController::class, 'edit'])
            ->name('amenities.edit');

        Route::put('amenities/{amenity:uuid}', [Admin\AmenityController::class, 'update'])
            ->name('amenities.update');

        Route::delete('amenities/{amenity:uuid}', [Admin\AmenityController::class, 'destroy'])
            ->name('amenities.destroy');

        Route::put('amenities/{amenity:uuid}/restore', [Admin\AmenityController::class, 'restore'])
            ->withTrashed()
            ->name('amenities.restore');

        Route::put('api/amenities/sort', [Admin\Api\AmenityController::class, 'updateSort'])
            ->name('api.amenities.sort');

        Route::get('deleted-amenities', [Admin\AmenityController::class, 'deletedIndex'])
            ->name('deleted-amenities.index');
    });

    // Calls-team inquiries queue — gated by `inquiries.manage`. Super-admin only
    // via Gate::before bypass; permission exists in DB but no explicit role grant
    // (calls-team role lands later).
    Route::middleware('can:' . AppPermission::inquiriesManage()->value)->group(function () {
        Route::get('filtered-inquiries', [Admin\InquiryController::class, 'filteredIndex'])
            ->name('filtered-inquiries.index');

        Route::put('inquiries/{inquiry:uuid}/reject', [Admin\InquiryController::class, 'reject'])
            ->name('inquiries.reject');

        Route::put('inquiries/{inquiry:uuid}/handoff', [Admin\InquiryController::class, 'handoff'])
            ->name('inquiries.handoff');

        Route::get('handoffs', [Admin\HandoffController::class, 'index'])
            ->name('handoffs.index');
    });

    // Vapor's signed S3 URL endpoint — browser calls this to get a pre-signed URL,
    // then PUTs the file directly to S3. Gated to admins so non-admins can't generate URLs.
    Route::post('vapor/signed-storage-url', [\Laravel\Vapor\Http\Controllers\SignedStorageUrlController::class, 'store'])
        ->name('vapor.signed-storage-url');
});
