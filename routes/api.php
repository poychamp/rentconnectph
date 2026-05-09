<?php

use App\Http\Controllers\Api\V1\Auth\FieldLoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Field\ListingController as FieldListingController;
use App\Http\Controllers\Api\V1\Field\Vapor\SignedStorageUrlController as FieldVaporSignedStorageUrlController;
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

    Route::middleware(['auth:sanctum', 'abilities:field'])
        ->prefix('field')->name('field.')->group(function () {
            Route::get('listings/queued', [FieldListingController::class, 'queued'])
                ->name('listings.queued');

            Route::get('listings/priority', [FieldListingController::class, 'priority'])
                ->name('listings.priority');

            Route::get('listings/submitted', [FieldListingController::class, 'submitted'])
                ->name('listings.submitted');

            Route::get('listings/verified', [FieldListingController::class, 'verified'])
                ->name('listings.verified');

            Route::patch('listings/priority-sort', [FieldListingController::class, 'prioritySort'])
                ->name('listings.priority-sort');

            Route::get('listings/{listing:uuid}', [FieldListingController::class, 'show'])
                ->name('listings.show');

            Route::patch('listings/{listing:uuid}', [FieldListingController::class, 'update'])
                ->name('listings.update');

            Route::patch('listings/{listing:uuid}/request-verification', [FieldListingController::class, 'requestVerification'])
                ->name('listings.request-verification');

            Route::patch('listings/{listing:uuid}/priority-toggle', [FieldListingController::class, 'priorityToggle'])
                ->name('listings.priority-toggle');

            Route::post('vapor/signed-storage-url', [FieldVaporSignedStorageUrlController::class, 'store'])
                ->name('vapor.signed-storage-url');
        });
});
