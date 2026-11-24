<?php

declare(strict_types=1);

require_once __DIR__.'/FinanceTestHelpers.php';
require_once __DIR__.'/../Leisure/LeisureTestHelpers.php';

use App\Actions\Bookings\BookQuotation;
use App\Enums\BookingStatus;
use App\Enums\ServiceCategory;
use App\Enums\StaffRole;
use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Mail\QuotationMail;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Quotation;
use App\Models\Service;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\QuotationDelivery;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(now('Africa/Johannesburg')->setDate(2027, 6, 1)->setTime(9, 0));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function (): void {
    ($this->undoRepeaterFake)();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quoteForm(array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Thandi Demo',
        'recipient_email' => 'thandi@example.test',
        'recipient_phone' => '082 555 0123',
        'arrival' => '2027-06-10',
        'departure' => '2027-06-12',
        'rooms' => [],
        'extras' => [],
        'services' => [],
        'valid_days' => 7,
    ], $overrides);
}

it('PAY-01 quotes room types, extras, spa and day passes from their own prices', function (): void {
    $room = financeRoomType();
    $breakfast = breakfast();
    $spa = leisureService(['name' => 'Full body massage', 'category' => ServiceCategory::Spa, 'price_cents' => 45_000]);
    $dayPass = leisureService(['name' => 'Pool day pass', 'category' => ServiceCategory::DayPass, 'price_cents' => 20_000, 'pricing' => Service::PER_BOOKING]);

    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm([
            'rooms' => [['accommodation_type_id' => $room->id, 'rooms' => 1, 'adults' => 2, 'children' => 0]],
            'extras' => [['extra_id' => $breakfast->id, 'quantity' => null]],
            'services' => [
                ['service_id' => $spa->id, 'people' => 2, 'sessions' => 1],
                ['service_id' => $dayPass->id, 'people' => 4, 'sessions' => 1],
            ],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $quotation = Quotation::query()->sole();
    $lines = $quotation->lines()->orderBy('sort_order')->get();

    // 2 nights × R1 000; breakfast 2 guests × 2 nights × R150; massage 2 × R450; day pass per booking R200.
    expect($lines->map(fn ($line): int => $line->total_cents->cents)->all())->toBe([200_000, 60_000, 90_000, 20_000])
        ->and($lines[1]->quantity + 0)->toEqual(4)
        ->and($quotation->total_cents->cents)->toBe(370_000)
        ->and($quotation->valid_until->toDateString())->toBe('2027-06-08')
        ->and(DB::table('quotations')->value('recipient_email'))->not->toContain('thandi')
        ->and($quotation->recipient_email)->toBe('thandi@example.test');

    $this->get($quotation->publicUrl())->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(app(DocumentPdf::class)->html($quotation))->toContain('alt="QR code for quotation '.$quotation->number.'"');
});

it('PAY-01 refuses a room type without a price, and rooms without dates, and creates nothing', function (): void {
    $unpriced = financeRoomType();
    $unpriced->update(['base_nightly_rate_cents' => 0]);

    // Unpriced room types are not offered on the form…
    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['rooms' => [['accommodation_type_id' => $unpriced->id, 'rooms' => 1, 'adults' => 2]]]))
        ->call('create')
        ->assertHasFormErrors(['rooms.0.accommodation_type_id']);

    // …and the pricing refuses them too.
    expect(fn () => QuotationResource::buildLines(quoteForm(['rooms' => [['accommodation_type_id' => $unpriced->id, 'rooms' => 1, 'adults' => 2]]])))
        ->toThrow(InvalidArgumentException::class, 'has no price yet');

    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['arrival' => null, 'departure' => null, 'rooms' => [['accommodation_type_id' => financeRoomType()->id, 'rooms' => 1, 'adults' => 2]]]))
        ->call('create')
        ->assertNotified('Quotation not created');

    expect(Quotation::query()->count())->toBe(0);
});

it('PAY-01 lists and searches quotations for reception, and keeps housekeeping out', function (): void {
    $service = leisureService();
    $make = fn (string $name) => Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['recipient_name' => $name, 'services' => [['service_id' => $service->id, 'people' => 1, 'sessions' => 1]]]))
        ->call('create');
    $make('Sipho Demo');
    $make('Anna Demo');
    [$first, $second] = Quotation::query()->orderBy('id')->get()->all();

    Livewire::test(ListQuotations::class)
        ->assertCanSeeTableRecords([$first, $second])
        ->searchTable('Anna')
        ->assertCanSeeTableRecords([$second])
        ->assertCanNotSeeTableRecords([$first]);

    expect(staffUser(StaffRole::HousekeepingWorker)->can('viewAny', Quotation::class))->toBeFalse();
});

it('PAY-01 downloads, emails with a message and opens WhatsApp for a quotation, but not once it has expired', function (): void {
    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['services' => [['service_id' => leisureService()->id, 'people' => 2, 'sessions' => 1]]]))
        ->call('create');
    $quotation = Quotation::query()->sole();

    Livewire::test(ViewQuotation::class, ['record' => $quotation->getRouteKey()])
        ->callAction('download')
        ->assertFileDownloaded($quotation->number.'.pdf')
        ->callAction('email', data: ['email' => 'thandi@example.test', 'message' => 'Rates include breakfast.'])
        ->assertHasNoActionErrors();

    Mail::assertSent(QuotationMail::class, function (QuotationMail $mail): bool {
        $mail->assertSeeInOrderInHtml(['Hello', 'Rates include breakfast.', 'View quotation']);

        return $mail->hasTo('thandi@example.test');
    });

    $url = app(QuotationDelivery::class)->whatsAppUrl($quotation, $quotation->recipient_phone);
    expect($url)->toStartWith('https://wa.me/27825550123?text=')
        ->and(rawurldecode($url))->toContain($quotation->publicUrl());

    $this->travelTo(now()->addDays(8));
    expect(auth()->user()->can('send', $quotation->fresh()))->toBeFalse()
        ->and($quotation->fresh()->statusLabel())->toBe('Expired');
});

it('PAY-04 books a quotation: all quoted rooms on one held booking, with extras and services on its invoice', function (): void {
    $type = AccommodationType::factory()->withRooms(2)->create(['name' => 'Garden Room', 'base_nightly_rate_cents' => 100_000, 'base_occupancy' => 2, 'max_occupancy' => 2]);
    $breakfast = breakfast();
    $spa = leisureService(['name' => 'Full body massage', 'category' => ServiceCategory::Spa, 'price_cents' => 45_000]);

    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm([
            'rooms' => [['accommodation_type_id' => $type->id, 'rooms' => 2, 'adults' => 2, 'children' => 0]],
            'extras' => [['extra_id' => $breakfast->id, 'quantity' => null]],
            'services' => [['service_id' => $spa->id, 'people' => 2, 'sessions' => 1]],
        ]))
        ->call('create');
    $quotation = Quotation::query()->sole();

    Livewire::test(ViewQuotation::class, ['record' => $quotation->getRouteKey()])
        ->callAction('book', data: ['first_name' => 'Thandi', 'last_name' => 'Demo', 'source' => 'phone', 'hold_until' => '2027-06-03 12:00'])
        ->assertHasNoActionErrors();

    $booking = Booking::query()->sole();
    $quotation->refresh();

    // 2 rooms × 2 nights × R1 000; breakfast 4 guests × 2 nights × R150; massage 2 × R450.
    expect($booking->status)->toBe(BookingStatus::Hold)
        ->and($booking->activeBookingRooms)->toHaveCount(2)
        ->and($booking->total_cents->cents)->toBe(400_000 + 120_000 + 90_000)
        ->and($booking->currentInvoice()->total_cents->equals($quotation->total_cents))->toBeTrue()
        ->and($booking->extras->pluck('description')->all())->toContain('Full body massage, 2 people (time to be arranged)')
        ->and($booking->lead_guest_email)->toBe('thandi@example.test')
        ->and($quotation->booking_id)->toBe($booking->id)
        ->and($quotation->statusLabel())->toBe('Booked')
        ->and(auth()->user()->can('book', $quotation))->toBeFalse();
});

it('PAY-04 warns when prices changed since the quote, and books nothing when the rooms are taken', function (): void {
    $type = financeRoomType();

    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['rooms' => [['accommodation_type_id' => $type->id, 'rooms' => 1, 'adults' => 2, 'children' => 0]]]))
        ->call('create');
    $quotation = Quotation::query()->sole();

    expect(app(BookQuotation::class)->priceChange($quotation))->toBeNull();
    $type->update(['base_nightly_rate_cents' => 120_000]);
    expect(app(BookQuotation::class)->priceChange($quotation)?->cents)->toBe(240_000);

    reserve($type);

    Livewire::test(ViewQuotation::class, ['record' => $quotation->getRouteKey()])
        ->callAction('book', data: ['first_name' => 'Thandi', 'last_name' => 'Demo', 'source' => 'phone', 'hold_until' => '2027-06-03 12:00'])
        ->assertNotified('Booking not created');

    expect($quotation->fresh()->booking_id)->toBeNull()
        ->and(Booking::query()->count())->toBe(1);
});

it('PAY-01 flags a quotation as emailed, with the address and how many times', function (): void {
    Livewire::test(CreateQuotation::class)
        ->fillForm(quoteForm(['services' => [['service_id' => leisureService()->id, 'people' => 1, 'sessions' => 1]], 'send_email' => true]))
        ->call('create');
    $quotation = Quotation::query()->sole();

    Livewire::test(ViewQuotation::class, ['record' => $quotation->getRouteKey()])
        ->callAction('email', data: ['email' => 'thandi@example.test'])
        ->assertSee('Sent 2× to thandi@example.test');

    Livewire::test(ListQuotations::class)->assertTableColumnStateSet('email_status', 'Sent 2× to thandi@example.test', $quotation);
});
