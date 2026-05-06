<?php

use App\Http\Controllers\Api\V1\Auth\FieldLoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/field-login', FieldLoginController::class)
        ->name('auth.field-login');

    Route::post('auth/logout', LogoutController::class)
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    Route::get('me', MeController::class)
        ->middleware('auth:sanctum')
        ->name('me');
});
