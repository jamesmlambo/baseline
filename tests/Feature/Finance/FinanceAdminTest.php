<?php

declare(strict_types=1);

require_once __DIR__.'/FinanceTestHelpers.php';

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\RefundStatus;
use App\Enums\StaffRole;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentLedger;
use App\Services\Payments\RefundWorkflow;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    seedFinanceSettings();
    Mail::fake();
});

it('REC-03 lets reception create a booking that holds a specific room until a chosen time', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $type = financeRoomType();
    $room = $type->rooms()->first();

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'accommodation_type_id' => $type->id,
            'arrival' => '2027-06-10',
            'departure' => '2027-06-12',
            'room_id' => $room->id,
            'adults' => 2,
            'first_name' => 'Walk',
            'last_name' => 'In',
            'phone' => '082 111 2222',
            'source' => 'walk_in',
            'hold_expires_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::query()->sole();

    expect($booking->status)->toBe(BookingStatus::Hold)
        ->and($booking->activeBookingRooms->first()->room_id)->toBe($room->id)
        ->and($booking->created_by)->toBe(auth()->id())
        ->and($booking->currentInvoice())->not->toBeNull();
});

it('explains when reception tries to book a room that is taken', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $type = financeRoomType();
    reserve($type);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'accommodation_type_id' => $type->id,
            'arrival' => '2027-06-10',
            'departure' => '2027-06-12',
            'adults' => 2,
            'first_name' => 'Late',
            'last_name' => 'Comer',
            'phone' => '082 111 3333',
            'source' => 'phone',
            'hold_expires_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertNotified('Booking not created');

    expect(Booking::query()->count())->toBe(1);
});

it('records cash from the booking screen and confirms the booking', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = reserve();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('recordPayment', ['method' => 'cash', 'amount' => '2000'])
        ->assertNotified('Payment recorded');

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lists bookings and shows a booking with its payments', function (): void {
    $this->actingAs(staffUser(StaffRole::Manager));
    $booking = reserve();
    startFakePayment($booking);

    Livewire::test(ListBookings::class)->assertCanSeeTableRecords([$booking]);
    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])->assertOk()->assertSee($booking->reference);
    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => ViewBooking::class])
        ->assertOk()
        ->assertCanSeeTableRecords($booking->payments);
});

it('cancels a booking with a reason and frees the room', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = reserve();

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Guest phoned to cancel'])
        ->assertNotified('Booking cancelled');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->activeBookingRooms()->count())->toBe(0);
});

it('lets a manager verify an EFT from the payments queue, but hides it from reception', function (): void {
    $booking = reserve(method: PaymentMethod::Eft);
    $payment = app(PaymentLedger::class)->recordEftNotice($booking, $booking->deposit_due_cents);

    $this->actingAs(staffUser(StaffRole::Receptionist));
    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->assertTableActionHidden('verifyEft', $payment);

    $this->actingAs(staffUser(StaffRole::Manager));
    Livewire::test(ListPayments::class)
        ->callTableAction('verifyEft', $payment, ['bank_reference' => 'Statement line 42', 'amount_seen' => '2000'])
        ->assertNotified('EFT verified');

    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('hides the approve button from whoever requested the refund', function (): void {
    $booking = reserve();
    $payment = startFakePayment($booking);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);
    $manager = staffUser(StaffRole::Manager);
    $refund = app(RefundWorkflow::class)->request($payment->fresh(), Money::cents(50_000), 'Early departure', $manager);

    $this->actingAs($manager);
    Livewire::test(ListRefunds::class)->assertTableActionHidden('approve', $refund);

    $this->actingAs(staffUser(StaffRole::Owner));
    Livewire::test(ListRefunds::class)
        ->callTableAction('approve', $refund)
        ->assertNotified('Refund processed');

    expect($refund->fresh()->status)->toBe(RefundStatus::Processed);
});

it('requests a refund from the booking payments tab', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = reserve();
    $payment = startFakePayment($booking);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $booking->fresh(), 'pageClass' => ViewBooking::class])
        ->callTableAction('requestRefund', $payment, ['amount' => '500', 'reason' => 'Breakfast not served'])
        ->assertNotified('Refund requested — waiting for a second approval');

    expect(Refund::query()->sole()->amount_cents->cents)->toBe(50_000);
});

it('finds a guest by exact email even though it is encrypted', function (): void {
    $this->actingAs(staffUser(StaffRole::Receptionist));
    $booking = reserve(email: 'findme@example.test');

    Livewire::test(ListGuests::class)
        ->searchTable('FindMe@Example.test')
        ->assertCanSeeTableRecords([$booking->guest]);
});

it('keeps finance screens away from field staff and content editors', function (StaffRole $role): void {
    $user = staffUser($role);

    expect($user->can('viewAny', Booking::class))->toBeFalse()
        ->and($user->can('viewAny', Payment::class))->toBeFalse()
        ->and($user->can('viewAny', Refund::class))->toBeFalse();
})->with([StaffRole::HousekeepingWorker, StaffRole::ContentEditor, StaffRole::Inspector]);

it('PEND-06 lets only the owner confirm without payment, and records why', function (): void {
    $booking = reserve();

    expect(staffUser(StaffRole::Manager)->can('confirmWithoutPayment', $booking))->toBeFalse();

    $this->actingAs(staffUser(StaffRole::Owner));
    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('confirmWithoutPayment', ['reason' => 'Corporate account, pays monthly'])
        ->assertNotified('Booking confirmed');

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->statusHistory()->get()->last()->reason)->toContain('Owner override');
});
