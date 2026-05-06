<?php

use App\Http\Controllers\Api\V1\Auth\FieldLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/field-login', FieldLoginController::class)
        ->name('auth.field-login');
});
