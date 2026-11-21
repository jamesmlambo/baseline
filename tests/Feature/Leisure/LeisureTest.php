<?php

declare(strict_types=1);

require_once __DIR__.'/LeisureTestHelpers.php';

use App\Enums\GuestRequestStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceCategory;
use App\Enums\StaffRole;
use App\Models\KitchenTicket;
use App\Models\MenuCategoryRule;
use App\Models\MenuItem;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingResource;
use App\Models\Setting;
use App\Services\Leisure\KitchenTickets;
use App\Services\Leisure\ServiceAvailability;
use App\Services\Leisure\ServiceBookings;
use App\Services\Portal\GuestRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(at('2027-06-10 08:00'));
});

function walkIn(Service $service, string $start, int $people = 2): ServiceBooking
{
    return app(ServiceBookings::class)->book($service, at($start), $people, ['name' => 'Day Visitor'], null, false, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::Receptionist));
}

// --- Slots, hours, capacity (LSR-02) ----------------------------------------------------

it('LSR-02 offers slots only inside opening hours, after the minimum notice', function (): void {
    $this->travelTo(at('2027-06-10 10:30'));
    $service = leisureService(['min_notice_minutes' => 60]);

    $slots = array_map(fn ($slot): string => $slot->setTimezone('Africa/Johannesburg')->format('H:i'), app(ServiceAvailability::class)->slots($service, at('2027-06-10'), 2));

    expect($slots)->toBe(['12:00', '13:00', '14:00', '15:00', '16:00']);
});

it('LSR-02 offers nothing on a closed day or for too large a group', function (): void {
    $service = leisureService(['opening_hours' => ['1' => ['09:00', '17:00']], 'max_party_size' => 4]);

    expect(app(ServiceAvailability::class)->slots($service, at('2027-06-10'), 2))->toBe([])
        ->and(app(ServiceAvailability::class)->slots($service, at('2027-06-14'), 5))->toBe([]);
});

it('LSR-02 never lets a slot go over its capacity in people', function (): void {
    $service = leisureService(['capacity' => 5]);
    walkIn($service, '2027-06-10 10:00', 4);

    expect(fn () => walkIn($service, '2027-06-10 10:00', 2))->toThrow(InvalidArgumentException::class)
        ->and(walkIn($service, '2027-06-10 10:00', 1)->status)->toBe(ServiceBookingStatus::Confirmed);
});

it('LSR-02 allows one group at a time in a VIP area', function (): void {
    $vip = leisureService(['category' => ServiceCategory::Vip, 'capacity_unit' => Service::CAPACITY_BOOKINGS, 'capacity' => 1, 'duration_minutes' => 180]);
    walkIn($vip, '2027-06-10 10:00');

    expect(fn () => walkIn($vip, '2027-06-10 12:00'))->toThrow(InvalidArgumentException::class)
        ->and(walkIn($vip, '2027-06-10 13:00')->status)->toBe(ServiceBookingStatus::Confirmed);
});

it('LSR-02 refuses times that are not offered slots', function (): void {
    $service = leisureService();

    expect(fn () => walkIn($service, '2027-06-10 10:15'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => walkIn($service, '2027-06-10 17:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => walkIn($service, '2027-06-09 10:00'))->toThrow(InvalidArgumentException::class);
});

// --- Spa resources (LSR-03) -------------------------------------------------------------

it('LSR-03 needs both a free therapist and a free treatment room', function (): void {
    $spa = spaService(therapists: 2, rooms: 1);
    $first = walkIn($spa, '2027-06-10 10:00', 1);

    expect($first->resourceAllocations()->count())->toBe(2)
        ->and(fn () => walkIn($spa, '2027-06-10 10:00', 1))->toThrow(InvalidArgumentException::class)
        ->and(walkIn($spa, '2027-06-10 11:00', 1)->status)->toBe(ServiceBookingStatus::Confirmed);
});

it('LSR-03 is protected by the database against double-booking a therapist', function (): void {
    $spa = spaService();
    $booking = walkIn($spa, '2027-06-10 10:00', 1);
    $held = $booking->resourceAllocations()->first();

    expect(fn () => ServiceBookingResource::query()->create([
        'service_booking_id' => $booking->id,
        'service_resource_id' => $held->service_resource_id,
        'starts_at' => at('2027-06-10 10:30'),
        'ends_at' => at('2027-06-10 11:30'),
    ]))->toThrow(QueryException::class);
});

it('LSR-03 frees the therapist and room when a booking is cancelled', function (): void {
    $spa = spaService();
    $booking = walkIn($spa, '2027-06-11 10:00', 1);

    app(ServiceBookings::class)->cancel($booking, 'Guest unwell', staffUser(StaffRole::Receptionist));

    expect(walkIn($spa, '2027-06-11 10:00', 1)->status)->toBe(ServiceBookingStatus::Confirmed);
});

// --- Charging and paying (LSR-05, LSR-02) -----------------------------------------------

it('LSR-05 charges an in-house guest\'s room once, when the service is done', function (): void {
    $stay = portalStay();
    $service = leisureService(['price_cents' => 25_000]);
    $receptionist = staffUser(StaffRole::Receptionist);
    $booking = app(ServiceBookings::class)->book($service, at('2027-06-10 10:00'), 2, ['name' => 'Lerato'], $stay, true, ServiceBooking::SOURCE_RECEPTION, $receptionist);

    expect($stay->fresh()->balanceDue()->cents)->toBe(0);

    app(ServiceBookings::class)->complete($booking, $receptionist);

    expect($stay->fresh()->balanceDue()->cents)->toBe(50_000)
        ->and($booking->fresh()->status)->toBe(ServiceBookingStatus::Completed)
        ->and(fn () => app(ServiceBookings::class)->complete($booking->fresh(), $receptionist))->toThrow(InvalidArgumentException::class);
});

it('LSR-05 only lets checked-in guests charge eligible services to the room', function (): void {
    $notAllowed = leisureService(['charge_to_room_allowed' => false]);
    $stay = portalStay();

    expect(fn () => app(ServiceBookings::class)->book($notAllowed, at('2027-06-10 10:00'), 1, ['name' => 'x'], $stay, true, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::Receptionist)))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ServiceBookings::class)->book(leisureService(), at('2027-06-10 10:00'), 1, ['name' => 'x'], reserve(email: 'future@example.test'), true, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::Receptionist)))
        ->toThrow(InvalidArgumentException::class);
});

it('LSR-02 takes cash from a walk-in with an invoice and receipt, and only then marks it done', function (): void {
    $receptionist = staffUser(StaffRole::Receptionist);
    $booking = walkIn(leisureService(['price_cents' => 5_000]), '2027-06-10 10:00', 3);

    expect(fn () => app(ServiceBookings::class)->complete($booking, $receptionist))->toThrow(InvalidArgumentException::class, 'Take payment first');

    app(ServiceBookings::class)->takePayment($booking, PaymentMethod::Cash, $receptionist);
    $booking->refresh();

    expect($booking->invoice->total_cents->cents)->toBe(15_000)
        ->and($booking->invoice->booking_id)->toBeNull()
        ->and($booking->invoice->recipient_name)->toBe('Day Visitor')
        ->and($booking->payment->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and(Receipt::query()->where('payment_id', $booking->payment_id)->exists())->toBeTrue()
        ->and(fn () => app(ServiceBookings::class)->takePayment($booking, PaymentMethod::Cash, $receptionist))->toThrow(InvalidArgumentException::class);

    app(ServiceBookings::class)->complete($booking, $receptionist);

    expect($booking->fresh()->status)->toBe(ServiceBookingStatus::Completed);
});

it('LSR-02 waits for verification of a walk-in EFT', function (): void {
    $booking = walkIn(leisureService(), '2027-06-10 10:00');

    app(ServiceBookings::class)->takePayment($booking, PaymentMethod::Eft, staffUser(StaffRole::Receptionist), 'REF123');

    expect($booking->fresh()->payment->status)->toBe(PaymentRecordStatus::PendingVerification);
});

// --- Cancellations and no-shows ---------------------------------------------------------

it('LSR-02 lets guests cancel only before the cut-off, while staff can cancel any time', function (): void {
    $stay = portalStay();
    $service = leisureService(['cancellation_cutoff_hours' => 24]);
    $tomorrow = app(ServiceBookings::class)->book($service, at('2027-06-11 15:00'), 1, ['name' => 'L'], $stay, true, ServiceBooking::SOURCE_PORTAL);
    $soon = app(ServiceBookings::class)->book($service, at('2027-06-10 15:00'), 1, ['name' => 'L'], $stay, true, ServiceBooking::SOURCE_PORTAL);

    app(ServiceBookings::class)->cancel($tomorrow, 'Changed plans');

    expect($tomorrow->fresh()->status)->toBe(ServiceBookingStatus::Cancelled)
        ->and(fn () => app(ServiceBookings::class)->cancel($soon, 'Changed plans'))->toThrow(InvalidArgumentException::class);

    app(ServiceBookings::class)->cancel($soon, 'Guest asked at reception', staffUser(StaffRole::Receptionist));
    expect($soon->fresh()->status)->toBe(ServiceBookingStatus::Cancelled);
});

it('LSR-02 does not cancel a booking that has been paid', function (): void {
    $booking = walkIn(leisureService(), '2027-06-11 10:00');
    app(ServiceBookings::class)->takePayment($booking, PaymentMethod::Cash, staffUser(StaffRole::Receptionist));

    expect(fn () => app(ServiceBookings::class)->cancel($booking->fresh(), 'x', staffUser(StaffRole::Receptionist)))->toThrow(InvalidArgumentException::class);
});

it('LSR-02 marks no-shows only after the start time', function (): void {
    $booking = walkIn(leisureService(), '2027-06-10 10:00');
    $receptionist = staffUser(StaffRole::Receptionist);

    expect(fn () => app(ServiceBookings::class)->noShow($booking, $receptionist))->toThrow(InvalidArgumentException::class);

    $this->travelTo(at('2027-06-10 10:30'));
    app(ServiceBookings::class)->noShow($booking, $receptionist);

    expect($booking->fresh()->status)->toBe(ServiceBookingStatus::NoShow);
});

it('LSR-01 keeps leisure bookings to the front desk', function (): void {
    expect(fn () => app(ServiceBookings::class)->book(leisureService(), at('2027-06-10 10:00'), 1, ['name' => 'x'], null, false, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::HousekeepingWorker)))
        ->toThrow(AuthorizationException::class);
});

// --- Feature flags (LSR-06) -------------------------------------------------------------

it('LSR-06 asks for the safety acknowledgement only when the feature is switched on', function (): void {
    $pool = leisureService(['requires_indemnity' => true]);

    expect(walkIn($pool, '2027-06-10 10:00')->indemnity_accepted_at)->toBeNull();

    Setting::query()->updateOrCreate(['key' => 'features.indemnity'], ['group' => 'features', 'label' => 'Indemnity', 'type' => 'string', 'value' => 'yes']);
    Cache::flush();

    expect(fn () => walkIn($pool, '2027-06-10 11:00'))->toThrow(InvalidArgumentException::class);

    $accepted = app(ServiceBookings::class)->book($pool, at('2027-06-10 11:00'), 1, ['name' => 'x'], null, false, ServiceBooking::SOURCE_RECEPTION, staffUser(StaffRole::Receptionist), indemnityAccepted: true);

    expect($accepted->indemnity_accepted_at)->not->toBeNull();
});

// --- Meals and the kitchen (LSR-04) -----------------------------------------------------

it('LSR-04 refuses meal orders outside the category\'s ordering hours, including overnight windows', function (): void {
    $stay = portalStay();
    $breakfast = MenuItem::query()->create(['category' => 'Breakfast', 'name' => 'Eggs', 'price_cents' => 8_000, 'is_available' => true, 'is_demo' => true]);
    MenuCategoryRule::query()->create(['category' => 'Breakfast', 'orders_open_at' => '18:00', 'orders_close_at' => '07:30']);

    expect(fn () => app(GuestRequests::class)->orderMeal($stay, [$breakfast->id => 1], null, true))->toThrow(InvalidArgumentException::class, '18:00–07:30');

    $this->travelTo(at('2027-06-10 21:00'));

    expect(app(GuestRequests::class)->orderMeal($stay, [$breakfast->id => 1], null, true)->status)->toBe(GuestRequestStatus::New);
});

it('LSR-04 sends room-service orders to the kitchen with allergens, and closes the ticket on delivery', function (): void {
    $stay = portalStay();
    $item = MenuItem::query()->create(['category' => 'Light meals', 'name' => 'Toastie', 'allergens' => 'Gluten, dairy', 'price_cents' => 7_000, 'is_available' => true, 'is_demo' => true]);
    $order = app(GuestRequests::class)->orderMeal($stay, [$item->id => 2], 'No onion', true);
    $ticket = KitchenTicket::query()->sole();

    expect($ticket->items)->toBe([['name' => 'Toastie', 'quantity' => 2]])
        ->and($ticket->allergens)->toContain('Gluten, dairy')
        ->and($ticket->notes)->toBe('No onion');

    app(KitchenTickets::class)->advance($ticket, KitchenTicketStatus::Preparing, staffUser(StaffRole::KitchenStaff));
    expect($order->fresh()->status)->toBe(GuestRequestStatus::Acknowledged);

    app(GuestRequests::class)->complete($order->fresh(), staffUser(StaffRole::Receptionist));
    expect($ticket->fresh()->status)->toBe(KitchenTicketStatus::Delivered);
});

it('LSR-04 does not let housekeeping update kitchen tickets', function (): void {
    $stay = portalStay();
    $item = MenuItem::query()->create(['category' => 'Light meals', 'name' => 'Toastie', 'price_cents' => 7_000, 'is_available' => true, 'is_demo' => true]);
    app(GuestRequests::class)->orderMeal($stay, [$item->id => 1], null, true);

    expect(fn () => app(KitchenTickets::class)->advance(KitchenTicket::query()->sole(), KitchenTicketStatus::Ready, staffUser(StaffRole::HousekeepingWorker)))
        ->toThrow(AuthorizationException::class);
});

it('LSR-04 sends booked meals to the kitchen for their time', function (): void {
    $dinner = leisureService(['category' => ServiceCategory::Meal, 'opening_hours' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], ['18:00', '21:00'])]);

    $booking = walkIn($dinner, '2027-06-10 19:00', 4);

    $ticket = KitchenTicket::query()->sole();
    expect($ticket->service_booking_id)->toBe($booking->id)
        ->and($ticket->items[0]['quantity'])->toBe(4)
        ->and($ticket->wanted_at->equalTo(at('2027-06-10 19:00')))->toBeTrue();

    app(ServiceBookings::class)->cancel($booking, 'Plans changed', staffUser(StaffRole::Receptionist));
    expect($ticket->fresh()->status)->toBe(KitchenTicketStatus::Cancelled);
});
