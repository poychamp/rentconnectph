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
});
