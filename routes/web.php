<?php

declare(strict_types=1);

use App\Http\Controllers\Site\AvailabilityController;
use App\Http\Controllers\Site\BookingEnquiryController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\FaqController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\PageController;
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

Route::get('/book', [BookingEnquiryController::class, 'create'])->name('book');
Route::post('/book', [BookingEnquiryController::class, 'store'])
    ->middleware('throttle:enquiries')
    ->name('book.store');

Route::get('/contact-us', [ContactController::class, 'show'])->name('contact');
Route::post('/contact-us', [ContactController::class, 'store'])
    ->middleware('throttle:enquiries')
    ->name('contact.store');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// CMS pages by slug (about-us, house-rules, ...). Must stay last before the fallback.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!(admin|staff|stay|livewire|storage|up|filament)\b)[a-z0-9-]+')
    ->name('page');

// Unknown URLs: follow an old-site redirect if one exists (WEB-05), otherwise 404.
Route::fallback(RedirectController::class);
