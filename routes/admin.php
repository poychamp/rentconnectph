<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
Route::get('/',     [Admin\DashboardController::class, 'index'])->name('dashboard');
