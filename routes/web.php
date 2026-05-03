<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('bfcache')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/listings/{listing:uuid}', [ListingController::class, 'show'])->name('listings.show');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/about', [AboutController::class, 'show'])->name('about');
    Route::get('/inquiries-success', [InquiryController::class, 'success'])->name('inquiries.success');
});

Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:inquiry-submit')
    ->name('inquiries.store');
