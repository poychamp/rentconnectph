<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::middleware('bfcache')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/listings/{listing:uuid}', [ListingController::class, 'show'])->name('listings.show');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/about', [AboutController::class, 'show'])->name('about');
    Route::get('/inquiries-success', [InquiryController::class, 'success'])->name('inquiries.success');
    Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
    Route::get('/contact-sent', [ContactController::class, 'sent'])->name('contact.sent');

    Route::get('/forgot-password', [PasswordController::class, 'request'])
        ->middleware('guest.admin')
        ->name('password.request');

    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])
        ->middleware('guest.admin')
        ->name('password.reset');
});

Route::post('/contact', [ContactController::class, 'send'])
    ->middleware('throttle:5,1')
    ->name('contact.send');

Route::post('/forgot-password', [PasswordController::class, 'email'])
    ->middleware(['guest.admin', 'throttle:5,1'])
    ->name('password.email');

Route::post('/reset-password', [PasswordController::class, 'update'])
    ->middleware(['guest.admin', 'throttle:5,1'])
    ->name('password.update');

Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:inquiry-submit')
    ->name('inquiries.store');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
