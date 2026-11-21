<?php

declare(strict_types=1);

use App\Http\Controllers\Dev\FakeGatewayController;
use App\Http\Controllers\IcalExportController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Site\AvailabilityController;
use App\Http\Controllers\Site\BookingController;
use App\Http\Controllers\Site\BookingPaymentController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\DocumentController;
use App\Http\Controllers\Site\FaqController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PriceListController;
use App\Http\Controllers\Site\RedirectController;
use App\Http\Controllers\Site\RoomController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{slug}', [RoomController::class, 'show'])->name('rooms.show');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

Route::get('/faqs', FaqController::class)->name('faqs');

Route::get('/check-availability', AvailabilityController::class)->name('availability');

Route::get('/book', [BookingController::class, 'create'])->name('book');
Route::post('/book', [BookingController::class, 'store'])
    ->middleware('throttle:enquiries')
    ->name('book.store');

// Guest booking pages, addressed by an unguessable token (no personal data in the URL).
Route::prefix('/booking/{token}')->where(['token' => '[A-Za-z0-9]{48}'])->group(function (): void {
    Route::get('/', [BookingController::class, 'show'])->name('booking.show');
    Route::post('/pay', [BookingPaymentController::class, 'pay'])->middleware('throttle:enquiries')->name('booking.pay');
    Route::post('/eft', [BookingPaymentController::class, 'eftNotice'])->middleware('throttle:enquiries')->name('booking.eft');
    Route::get('/return', [BookingPaymentController::class, 'return'])->name('booking.return');
});

// WhatsApp price lists (WA-01): short path, unguessable token.
Route::get('/p/{token}', [PriceListController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('price-list.show');

Route::get('/documents/{type}/{token}', DocumentController::class)
    ->where(['type' => 'invoice|receipt', 'token' => '[A-Za-z0-9]{48}'])
    ->name('documents.show');

Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');

if (! app()->isProduction()) {
    Route::get('/dev/fake-gateway', [FakeGatewayController::class, 'show'])->middleware('signed')->name('dev.fake-gateway.show');
    Route::post('/dev/fake-gateway', [FakeGatewayController::class, 'complete'])->middleware('signed')->name('dev.fake-gateway.complete');
}

require __DIR__.'/portal.php';

// CHN-02: per-room availability calendar for a channel (secret link, dates only).
Route::get('/ical/{token}.ics', IcalExportController::class)
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:60,1')
    ->name('ical.export');

Route::get('/contact-us', [ContactController::class, 'show'])->name('contact');
Route::post('/contact-us', [ContactController::class, 'store'])
    ->middleware('throttle:enquiries')
    ->name('contact.store');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// CMS pages by slug (about-us, house-rules, ...). Must stay last before the fallback.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!(?:admin|staff|stay|livewire|storage|up|filament|booking|documents|webhooks|dev|ical)$)[a-z0-9-]+')
    ->name('page');

// Unknown URLs: follow an old-site redirect if one exists (WEB-05), otherwise 404.
Route::fallback(RedirectController::class);
