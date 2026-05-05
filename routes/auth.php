<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

// Shared login flow for the admin guard — URL `/auth/login`. Both
// admin and field-officer roles authenticate here; the controller
// branches the post-login redirect by role (admin → /admin, field
// → /field). Already-authed admins bounce away via guest.admin.
Route::middleware('guest.admin')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Admin\AuthController::class, 'login'])->name('login.attempt');
});

// Self-service profile management for any authed admin-guard user
// (super-admin / admin / field officer / future calls-team). Two
// independent forms on one page — name (no current-password gate)
// and password (gated by current_password:admin). Each PUT validates
// into its own named bag so failures don't clobber the other card.
Route::middleware('auth:admin')->group(function () {
    Route::get('profile', [Admin\ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/name', [Admin\ProfileController::class, 'updateName'])->name('profile.name');
    Route::put('profile/password', [Admin\ProfileController::class, 'updatePassword'])->name('profile.password');
});
