<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';
require_once __DIR__.'/../Bookings/BookingTestHelpers.php';

use App\Actions\Inventory\BlockRoom;
use App\Actions\Reception\AddCharge;
use App\Actions\Reception\CheckIn;
use App\Actions\Reception\CheckOut;
use App\Actions\Reception\ExtendStay;
use App\Actions\Reception\ResolveReceptionCode;
use App\Enums\BookingStatus;
use App\Enums\FrontDeskLogType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoomBlockReason;
use App\Enums\StaffRole;
use App\Exceptions\OutstandingBalanceException;
use App\Exceptions\RoomUnavailableException;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\Availability\AvailabilityService;
use App\Services\Payments\PaymentLedger;
use App\Services\Reception\RoomCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
});

/**
 * A booking arriving today (10 Jun) for two nights, paid in full by cash.
 */
function paidArrival(): Booking
{
    $booking = reserve();
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));

    return $booking->fresh();
}

function checkedIn(): Booking
{
    $booking = paidArrival();
    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);

    return $booking->fresh();
}

// --- Check-in (REC-03, REC-04) ---------------------------------------------------------

it('REC-03 checks in a confirmed guest and records the register, key and time', function (): void {
    $booking = paidArrival();
    $receptionist = staffUser(StaffRole::Receptionist);

    app(CheckIn::class)->handle($booking, $receptionist, [
        'full_name' => 'Lerato Demo',
        'id_type' => 'sa_id',
        'id_number' => '8001015009087',
        'nationality' => 'South African',
    ], keyIssued: true, keyLabel: 'Key 4');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CheckedIn)
        ->and($booking->checked_in_by)->toBe($receptionist->id)
        ->and($booking->checked_in_at)->not->toBeNull()
        ->and($booking->reception_code)->toHaveLength(32)
        ->and($booking->registerEntry->maskedIdNumber())->toBe('•••••••••9087')
        ->and($booking->frontDeskLogs()->sole()->type)->toBe(FrontDeskLogType::KeyIssued);
});

it('REC-04 stores ID numbers encrypted', function (): void {
    $booking = paidArrival();

    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo', 'id_number' => '8001015009087']);

    expect(DB::table('guest_register_entries')->value('id_number'))->not->toContain('8001015009087');
});

it('REC-04 requires the register fields named in the setting', function (): void {
    Setting::query()->where('key', 'register.required_fields')->firstOrFail()->update(['value' => ['full_name', 'id_number']]);

    app(CheckIn::class)->handle(paidArrival(), staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);
})->throws(InvalidArgumentException::class, 'id number');

it('refuses to check in a booking that is only on hold', function (): void {
    app(CheckIn::class)->handle(reserve(), staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);
})->throws(InvalidArgumentException::class, 'Only confirmed bookings');

it('refuses to check in before the arrival date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-06-09 14:00', 'Africa/Johannesburg'));

    app(CheckIn::class)->handle(paidArrival(), staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);
})->throws(InvalidArgumentException::class, 'only due on');

it('does not let field staff check guests in', function (): void {
    app(CheckIn::class)->handle(paidArrival(), staffUser(StaffRole::HousekeepingWorker), ['full_name' => 'Lerato Demo']);
})->throws(AuthorizationException::class);

// --- Check-out (CHK-01/02) -------------------------------------------------------------

it('CHK-02 checks out a guest who owes nothing', function (): void {
    $booking = checkedIn();

    $result = app(CheckOut::class)->handle($booking, staffUser(StaffRole::Receptionist));

    expect($result['booking']->status)->toBe(BookingStatus::CheckedOut)
        ->and($result['booking']->checked_out_at)->not->toBeNull();
});

it('CHK-02 blocks check-out while money is owed', function (): void {
    $booking = checkedIn();
    app(AddCharge::class)->handle($booking, 'Minibar', 2, Money::cents(3_000), staffUser(StaffRole::Receptionist));

    expect(fn () => app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist)))
        ->toThrow(OutstandingBalanceException::class, 'R 60.00');

    expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn);
});

it('PEND-06 lets only the owner override the balance check, with a reason', function (): void {
    $booking = checkedIn();
    app(AddCharge::class)->handle($booking, 'Minibar', 1, Money::cents(3_000), staffUser(StaffRole::Receptionist));

    expect(fn () => app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Manager), 'Will pay by EFT'))
        ->toThrow(AuthorizationException::class);

    $result = app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Owner), 'Regular corporate guest');

    expect($result['booking']->status)->toBe(BookingStatus::CheckedOut)
        ->and($booking->statusHistory()->get()->last()->reason)->toContain('owner override: Regular corporate guest');
});

it('reports keys that were never returned', function (): void {
    $booking = paidArrival();
    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo'], keyIssued: true);

    expect(app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist))['keys_outstanding'])->toBe(1);
});

// --- Extend stay, charges (REC-03) -------------------------------------------------------

it('extends a stay with a supplementary invoice and updates what is owed', function (): void {
    $booking = checkedIn();

    $invoice = app(ExtendStay::class)->handle($booking, CarbonImmutable::parse('2027-06-13'), staffUser(StaffRole::Receptionist));
    $booking->refresh();

    expect($invoice->total_cents->cents)->toBe(100_000)
        ->and($booking->departure_date->toDateString())->toBe('2027-06-13')
        ->and($booking->activeBookingRooms()->sole()->departure_date->toDateString())->toBe('2027-06-13')
        ->and($booking->balanceDue()->cents)->toBe(100_000)
        ->and($booking->payment_status)->toBe(PaymentStatus::PartPaid)
        ->and(app(AvailabilityService::class)->isRoomAvailable($booking->activeBookingRooms()->sole()->room, stay('2027-06-12', '2027-06-13')))->toBeFalse();
});

it('refuses an extension when the room is taken and changes nothing', function (): void {
    $type = financeRoomType();
    $booking = reserve($type);
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));
    bookType($type, stay('2027-06-12', '2027-06-14'));

    expect(fn () => app(ExtendStay::class)->handle($booking->fresh(), CarbonImmutable::parse('2027-06-13'), staffUser(StaffRole::Receptionist)))
        ->toThrow(RoomUnavailableException::class);

    expect($booking->fresh()->departure_date->toDateString())->toBe('2027-06-12')
        ->and($booking->invoices()->count())->toBe(1);
});

it('adds a charge on its own invoice', function (): void {
    $booking = checkedIn();

    $invoice = app(AddCharge::class)->handle($booking, 'Extra towels', 2, Money::cents(2_500), staffUser(StaffRole::Receptionist));

    expect($invoice->lines()->sole()->description)->toBe('Extra towels')
        ->and($booking->fresh()->balanceDue()->cents)->toBe(5_000);
});

// --- Document QR (REC-06/07) -------------------------------------------------------------

it('REC-07 finds a booking from its document QR, logs the scan, and stops old codes after replacement', function (): void {
    $booking = paidArrival();
    $user = staffUser(StaffRole::Receptionist);
    $url = ResolveReceptionCode::urlFor($booking);
    $resolver = app(ResolveReceptionCode::class);

    expect($url)->not->toContain($booking->reference)
        ->and($resolver->handle($url, $user)?->id)->toBe($booking->id)
        ->and($resolver->handle($booking->fresh()->reception_code, $user)?->id)->toBe($booking->id)
        ->and(Activity::query()->where('event', 'document_scanned')->count())->toBe(2);

    ResolveReceptionCode::regenerate($booking, $user);

    expect($resolver->handle($url, $user))->toBeNull();
});

it('ignores anything that is not a booking code', function (string $scanned): void {
    expect(app(ResolveReceptionCode::class)->handle($scanned, staffUser(StaffRole::Receptionist)))->toBeNull();
})->with(['https://example.test/', 'BL-ABC123', "' OR 1=1 --", str_repeat('a', 32)]);

// --- Room calendar (REC-02) --------------------------------------------------------------

it('REC-02 shows holds, confirmed stays, guests in house and blocks on the right nights', function (): void {
    $type = financeRoomType();
    $hold = reserve($type, arrival: '2027-06-10', departure: '2027-06-11');
    $room = $type->rooms()->first();
    app(BlockRoom::class)->handle($room, stay('2027-06-12', '2027-06-13'), RoomBlockReason::Maintenance);

    $grid = app(RoomCalendar::class)->build(CarbonImmutable::parse('2027-06-10'), 4);
    $cells = collect($grid['rooms'])->firstWhere('room.id', $room->id)['cells'];

    expect(collect($cells)->pluck('kind')->all())->toBe(['hold', 'free', 'block', 'free'])
        ->and($cells[0]['booking']->id)->toBe($hold->id)
        ->and($grid['dates'])->toHaveCount(4);
});
