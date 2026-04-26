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

    Route::get('listings/admin-create', [Admin\ListingController::class, 'adminCreate'])
        ->name('listings.admin-create');
    Route::post('listings/admin-create', [Admin\ListingController::class, 'adminStore'])
        ->name('listings.admin-store');

    // Vapor's signed S3 URL endpoint — browser calls this to get a pre-signed URL,
    // then PUTs the file directly to S3. Gated to admins so non-admins can't generate URLs.
    Route::post('vapor/signed-storage-url', [\Laravel\Vapor\Http\Controllers\SignedStorageUrlController::class, 'store'])
        ->name('vapor.signed-storage-url');
});
