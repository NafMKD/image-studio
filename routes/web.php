<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'studio' : 'login'))->name('home');
Route::post('/webhooks/pixelforge', [WebhookController::class, 'store'])->middleware('throttle:120,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/dashboard', '/studio')->name('dashboard'); // Fortify login landing.
    Route::get('/studio', [StudioController::class, 'show'])->name('studio');
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/studio/estimate', [StudioController::class, 'estimate'])->middleware('throttle:90,1');
    Route::get('/studio/{conversation}', [StudioController::class, 'show'])->name('studio.show');
    Route::post('/studio/generations', [GenerationController::class, 'store'])->middleware('throttle:12,1');
    Route::post('/credits', [CreditController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/admin', [AdminController::class, 'index'])->middleware('can:super-admin')->name('admin');
    Route::get('/admin/usage', [AdminController::class, 'usage'])->middleware(['can:super-admin', 'throttle:10,1']);
});

require __DIR__.'/settings.php';
