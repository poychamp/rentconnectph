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
