<?php

declare(strict_types=1);

require_once __DIR__.'/LeisureTestHelpers.php';

use App\Enums\KitchenTicketStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\ServiceBookings\Pages\ListServiceBookings;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Filament\Staff\Resources\Kitchen\Pages\ListKitchenTickets;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Services\Leisure\ServiceBookings;
use App\Services\Portal\GuestRequests;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(at('2027-06-10 08:00'));
});

// --- Guest portal (GST-07, LSR-05) ------------------------------------------------------

it('LSR-05 lets an in-house guest book from the portal and charge it to the room', function (): void {
    $stay = portalStay();
    $spa = spaService();
    signInToPortal(portalRoom($stay));

    $this->get(route('portal.book', ['service' => $spa->id, 'date' => '2027-06-10', 'people' => 1]))
        ->assertOk()
        ->assertSee($spa->name)
        ->assertSee('10:00');

    $this->post(route('portal.book.store'), ['service_id' => $spa->id, 'start' => at('2027-06-10 10:00')->toIso8601String(), 'people' => 1])
        ->assertRedirect(route('portal.book'));

    $booking = ServiceBooking::query()->sole();
    expect($booking->booking_id)->toBe($stay->id)
        ->and($booking->charge_to_room)->toBeTrue()
        ->and($booking->source)->toBe(ServiceBooking::SOURCE_PORTAL);

    $this->post(route('portal.book.cancel', $booking->id))->assertRedirect(route('portal.book'));
    expect($booking->fresh()->status)->toBe(ServiceBookingStatus::Confirmed);
});

it('GST-07 hides services that are only bookable at reception', function (): void {
    $stay = portalStay();
    $reception = leisureService(['name' => 'Reception-only thing', 'bookable_by_guests' => false]);
    signInToPortal(portalRoom($stay));

    $this->get(route('portal.book'))->assertOk()->assertDontSee('Reception-only thing');
    $this->post(route('portal.book.store'), ['service_id' => $reception->id, 'start' => at('2027-06-10 10:00')->toIso8601String(), 'people' => 1])
        ->assertSessionHasErrors('service_id');
});

it('GST-06 does not let a guest cancel someone else\'s booking', function (): void {
    $stay = portalStay();
    $other = app(ServiceBookings::class)->book(leisureService(), at('2027-06-12 10:00'), 1, ['name' => 'Other'], null, false, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::Receptionist));
    signInToPortal(portalRoom($stay));

    $this->post(route('portal.book.cancel', $other->id))->assertNotFound();
});

// --- Staff screens ----------------------------------------------------------------------

it('LSR-01 lets reception book a walk-in from the leisure screen', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $pool = leisureService();

    Livewire::test(ListServiceBookings::class)
        ->callAction('newBooking', [
            'service_id' => $pool->id,
            'date' => '2027-06-10',
            'party_size' => 2,
            'start' => at('2027-06-10 11:00')->toIso8601String(),
            'customer_name' => 'Day Visitor',
        ])
        ->assertNotified('Booked');

    $booking = ServiceBooking::query()->sole();
    expect($booking->party_size)->toBe(2)->and($booking->charge_to_room)->toBeFalse();

    Livewire::test(ListServiceBookings::class)
        ->callTableAction('takePayment', $booking, ['method' => 'cash'])
        ->assertNotified('Payment recorded');

    expect($booking->fresh()->invoice_id)->not->toBeNull();
});

it('LSR-02 lets a manager set up a service with opening hours', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(staffUser(StaffRole::Manager));

    Livewire::test(ManageServices::class)
        ->callAction('create', [
            'name' => 'Sunset braai',
            'slug' => 'sunset-braai',
            'category' => 'braai',
            'price_cents' => '200.00',
            'pricing' => Service::PER_BOOKING,
            'duration_minutes' => 120,
            'slot_interval_minutes' => 60,
            'min_notice_minutes' => 0,
            'capacity_unit' => Service::CAPACITY_BOOKINGS,
            'capacity' => 2,
            'max_party_size' => 12,
            'cancellation_cutoff_hours' => 24,
            'opening_hours' => ['6' => ['16:00', '20:00'], '1' => [null, null]],
        ])
        ->assertHasNoActionErrors();

    $service = Service::query()->where('slug', 'sunset-braai')->sole();
    expect($service->opening_hours)->toBe(['6' => ['16:00', '20:00']])
        ->and($service->price_cents->cents)->toBe(20_000);
});

it('LSR-04 shows the kitchen its tickets and lets it mark them ready', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $item = MenuItem::query()->create(['category' => 'Light meals', 'name' => 'Toastie', 'allergens' => 'Gluten', 'price_cents' => 7_000, 'is_available' => true, 'is_demo' => true]);
    app(GuestRequests::class)->orderMeal(portalStay(), [$item->id => 1], null, true);
    $ticket = KitchenTicket::query()->sole();
    $this->actingAs(staffUser(StaffRole::KitchenStaff));

    Livewire::test(ListKitchenTickets::class)
        ->assertCanSeeTableRecords([$ticket])
        ->assertSee('Gluten')
        ->callTableAction('ready', $ticket);

    expect($ticket->fresh()->status)->toBe(KitchenTicketStatus::Ready);
});

it('LSR-04 keeps the kitchen queue away from staff who are not involved', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $this->actingAs(staffUser(StaffRole::HousekeepingWorker));

    Livewire::test(ListKitchenTickets::class)->assertForbidden();
});
