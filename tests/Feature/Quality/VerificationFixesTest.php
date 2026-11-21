<?php

declare(strict_types=1);

require_once __DIR__.'/../Leisure/LeisureTestHelpers.php';

use App\Actions\Staff\SyncStaffRoles;
use App\Actions\Website\RecordEnquiry;
use App\Enums\BookingStatus;
use App\Enums\EnquiryType;
use App\Enums\MessageChannel;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\Leisure\ServiceBookings;
use App\Services\Messaging\Providers\LogProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
    $this->travelTo(at('2027-06-10 08:00'));
});

// --- Role escalation (SEC-06) -----------------------------------------------------------

it('SEC-06 does not let an administrator give anyone the Owner role, including themselves', function (): void {
    $admin = staffUser(StaffRole::Administrator);

    expect(fn () => app(SyncStaffRoles::class)->handle($admin, [StaffRole::Administrator->value, StaffRole::Owner->value], $admin))
        ->toThrow(ValidationException::class)
        ->and($admin->fresh()->hasRole(StaffRole::Owner->value))->toBeFalse();

    $other = User::factory()->create();
    expect(fn () => app(SyncStaffRoles::class)->handle($other, [StaffRole::Owner->value], $admin))->toThrow(ValidationException::class);
});

it('SEC-06 lets an owner give and remove the Owner role, and other roles stay manageable by administrators', function (): void {
    $owner = staffUser(StaffRole::Owner);
    $admin = staffUser(StaffRole::Administrator);
    $other = User::factory()->create();

    app(SyncStaffRoles::class)->handle($other, [StaffRole::Owner->value], $owner);
    expect($other->fresh()->hasRole(StaffRole::Owner->value))->toBeTrue();

    $receptionist = staffUser(StaffRole::Receptionist);
    app(SyncStaffRoles::class)->handle($receptionist, [StaffRole::Receptionist->value, StaffRole::Manager->value], $admin);
    expect($receptionist->fresh()->hasRole(StaffRole::Manager->value))->toBeTrue()
        ->and(fn () => app(SyncStaffRoles::class)->handle($other->fresh(), [StaffRole::Manager->value], $admin))->toThrow(ValidationException::class);
});

it('SEC-06 ignores role names that do not exist', function (): void {
    $user = User::factory()->create();

    app(SyncStaffRoles::class)->handle($user, [StaffRole::Receptionist->value, 'super_admin'], staffUser(StaffRole::Owner));

    expect($user->fresh()->getRoleNames()->all())->toBe([StaffRole::Receptionist->value]);
});

// --- Encryption at rest (SEC-07) --------------------------------------------------------

it('SEC-07 stores booking and enquiry contact details encrypted, and keeps them out of the audit log', function (): void {
    $booking = reserve(email: 'secret.guest@example.test');
    $booking->forceFill(['status' => BookingStatus::Cancelled])->save();
    $enquiry = app(RecordEnquiry::class)->handle(EnquiryType::Contact, ['name' => 'A', 'email' => 'enquirer@example.test', 'phone' => '0825550199', 'message' => 'Hi'], null);

    $rawBooking = DB::table('bookings')->where('id', $booking->id)->first();
    $rawEnquiry = DB::table('enquiries')->where('id', $enquiry->id)->first();

    expect($rawBooking->lead_guest_email)->not->toContain('secret.guest')
        ->and($rawBooking->lead_guest_phone)->not->toContain('555')
        ->and(Booking::query()->find($booking->id)->lead_guest_email)->toBe('secret.guest@example.test')
        ->and($rawEnquiry->email)->not->toContain('enquirer')
        ->and($rawEnquiry->phone)->not->toContain('0825550199')
        ->and(Activity::query()->where('subject_type', Booking::class)->where('properties', 'like', '%secret.guest%')->count())->toBe(0);
});

// --- Logs (PRIV-01) ---------------------------------------------------------------------

it('PRIV-01 masks numbers and codes in the message log outside local development', function (): void {
    Log::spy();

    (new LogProvider(MessageChannel::Sms))->send('+27825550101', 'Your code is 482913.');

    Log::shouldHaveReceived('info')->withArgs(fn (string $line): bool => ! str_contains($line, '482913') && ! str_contains($line, '27825550') && str_contains($line, '101'));
});

// --- Duplicate payments (LSR-02) --------------------------------------------------------

it('LSR-02 never takes payment twice for the same walk-in booking, and won\'t cancel it once paid', function (): void {
    $receptionist = staffUser(StaffRole::Receptionist);
    $booking = app(ServiceBookings::class)->book(leisureService(), at('2027-06-11 10:00'), 2, ['name' => 'Walk In'], null, false, ServiceBooking::SOURCE_RECEPTION, $receptionist);
    $stale = ServiceBooking::query()->find($booking->id);

    app(ServiceBookings::class)->takePayment($booking, PaymentMethod::Cash, $receptionist);

    // A second request that loaded the booking before the first payment was saved.
    expect(fn () => app(ServiceBookings::class)->takePayment($stale, PaymentMethod::Cash, $receptionist))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ServiceBookings::class)->cancel($stale, 'Changed mind', $receptionist))->toThrow(InvalidArgumentException::class)
        ->and(DB::table('payments')->count())->toBe(1)
        ->and(DB::table('invoices')->whereNull('booking_id')->count())->toBe(1);
});
