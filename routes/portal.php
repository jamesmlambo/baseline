<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\PortalAccessController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalLeisureController;
use App\Http\Middleware\EnsurePortalStay;
use Illuminate\Support\Facades\Route;

/*
| Guest portal (FINAL_SPECIFICATION §13), under /stay. Sign-in pages are public but rate limited;
| everything else needs a valid stay session.
*/
Route::prefix('stay')->name('portal.')->group(function (): void {
    Route::get('/', [PortalAccessController::class, 'start'])->name('start');
    Route::get('/r/{token}', [PortalAccessController::class, 'room'])->where('token', '[A-Za-z0-9]{32}')->name('room');
    Route::post('/r/{token}', [PortalAccessController::class, 'sendCode'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:portal-codes')->name('room.code');
    Route::get('/r/{token}/verify', [PortalAccessController::class, 'verifyForm'])->where('token', '[A-Za-z0-9]{32}')->name('room.verify');
    Route::post('/r/{token}/verify', [PortalAccessController::class, 'verify'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:portal-verify')->name('room.verify.submit');
    Route::get('/link/{booking}', [PortalAccessController::class, 'link'])->whereNumber('booking')->middleware('signed')->name('link');
    Route::post('/sign-out', [PortalAccessController::class, 'signOut'])->name('sign-out');

    Route::middleware(EnsurePortalStay::class)->group(function (): void {
        Route::get('/home', [PortalController::class, 'home'])->name('home');
        Route::get('/requests', [PortalController::class, 'requests'])->name('requests');
        Route::post('/requests', [PortalController::class, 'storeRequest'])->middleware('throttle:portal-actions')->name('requests.store');
        Route::get('/menu', [PortalController::class, 'menu'])->name('menu');
        Route::post('/menu', [PortalController::class, 'order'])->middleware('throttle:portal-actions')->name('menu.order');
        Route::get('/messages', [PortalController::class, 'messages'])->name('messages');
        Route::post('/messages', [PortalController::class, 'sendMessage'])->middleware('throttle:portal-actions')->name('messages.send');
        Route::get('/book', [PortalLeisureController::class, 'index'])->name('book');
        Route::post('/book', [PortalLeisureController::class, 'store'])->middleware('throttle:portal-actions')->name('book.store');
        Route::post('/book/{serviceBooking}/cancel', [PortalLeisureController::class, 'cancel'])->whereNumber('serviceBooking')->name('book.cancel');
        Route::get('/bill', [PortalController::class, 'bill'])->name('bill');
        Route::post('/checkout', [PortalController::class, 'checkout'])->name('checkout');
        Route::get('/review', [PortalController::class, 'review'])->name('review');
        Route::post('/review', [PortalController::class, 'storeReview'])->middleware('throttle:portal-actions')->name('review.store');
        Route::get('/guests', [PortalController::class, 'phones'])->name('phones');
        Route::post('/guests', [PortalController::class, 'addPhone'])->middleware('throttle:portal-actions')->name('phones.add');
        Route::post('/guests/{phone}/remove', [PortalController::class, 'removePhone'])->whereNumber('phone')->name('phones.remove');
    });
});
