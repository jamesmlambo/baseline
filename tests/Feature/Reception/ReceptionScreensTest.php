<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Reception\ResolveReceptionCode;
use App\Enums\BookingStatus;
use App\Enums\FrontDeskLogType;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Filament\Pages\ArrivalsSheet;
use App\Filament\Pages\RoomCalendarPage;
use App\Filament\Pages\ScanDocument;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\FrontDeskLogsRelationManager;
use App\Filament\Widgets\ArrivalsToday;
use App\Filament\Widgets\ReceptionStats;
use App\Models\Booking;
use App\Models\FrontDeskLog;
use App\Services\Payments\PaymentLedger;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

function confirmedArrival(): Booking
{
    $booking = reserve();
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));

    return $booking->fresh();
}

it('checks a guest in from the booking screen', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('checkIn', ['full_name' => 'Lerato Demo', 'nationality' => 'South African', 'key_issued' => true, 'key_label' => 'Key 4'])
        ->assertNotified('Guest checked in');

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn)
        ->and($booking->registerEntry()->sole()->nationality)->toBe('South African');
});

it('shows why check-out is blocked when money is owed', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('checkIn', ['full_name' => 'Lerato Demo'])
        ->callAction('addCharge', ['description' => 'Minibar', 'quantity' => 1, 'unit_price' => '45'])
        ->assertNotified('Charge added')
        ->callAction('checkOut')
        ->assertNotified('Not done');

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn);
});

it('logs and resolves a complaint', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('logEvent', ['type' => FrontDeskLogType::Complaint->value, 'details' => 'Noisy neighbours'])
        ->assertNotified('Logged');

    $log = FrontDeskLog::query()->sole();

    Livewire::test(FrontDeskLogsRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => ViewBooking::class])
        ->assertCanSeeTableRecords([$log])
        ->callTableAction('resolve', $log);

    expect($log->fresh()->resolved_at)->not->toBeNull()
        ->and(FrontDeskLog::query()->open()->count())->toBe(0);
});

it('opens the booking straight from a scanned document link', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::withQueryParams(['code' => ResolveReceptionCode::ensureCode($booking)])
        ->test(ScanDocument::class)
        ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking]));
});

it('says so when a scanned code is unknown', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(ScanDocument::class)
        ->set('scanned', str_repeat('Z', 32))
        ->call('lookup')
        ->assertNotified('No booking found')
        ->assertNoRedirect();
});

it('shows the dashboard numbers and today\'s arrivals to reception', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(ReceptionStats::class)->assertOk()->assertSee('Arrivals today');
    Livewire::test(ArrivalsToday::class)->assertCanSeeTableRecords([$booking]);
});

it('renders the room calendar and moves between weeks', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(RoomCalendarPage::class)
        ->assertOk()
        ->assertSee($booking->lead_guest_name)
        ->call('move', 7)
        ->assertSet('start', '2027-06-16');
});

it('prints an arrivals sheet without contact details', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = confirmedArrival();

    Livewire::test(ArrivalsSheet::class)
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertSee($booking->lead_guest_name)
        ->assertDontSee('guest@example.test')
        ->assertDontSee('+27825550101');
});

it('keeps reception pages away from field staff and content editors', function (StaffRole $role): void {
    $this->actingAs(staffUser($role));

    expect(ScanDocument::canAccess())->toBeFalse()
        ->and(RoomCalendarPage::canAccess())->toBeFalse()
        ->and(ArrivalsSheet::canAccess())->toBeFalse()
        ->and(ReceptionStats::canView())->toBeFalse();
})->with([StaffRole::HousekeepingWorker, StaffRole::ContentEditor]);
