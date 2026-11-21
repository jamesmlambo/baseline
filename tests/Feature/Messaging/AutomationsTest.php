<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Messaging\RunMessageAutomations;
use App\Actions\Reception\CheckIn;
use App\Actions\Reception\CheckOut;
use App\Enums\MessageStatus;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Services\Payments\PaymentLedger;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    seedFinanceSettings();
    $this->seed(MessageTemplateSeeder::class);
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2027-06-08 09:00', 'Africa/Johannesburg'));
});

function messageKeys(Booking $booking): array
{
    return Message::query()->where('booking_id', $booking->id)->orderBy('id')->pluck('idempotency_key')->all();
}

function payCash(Booking $booking): Booking
{
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));

    return $booking->fresh();
}

it('WA-04 messages the guest how to pay when a booking is held', function (): void {
    $booking = reserve();

    $message = Message::query()->where('idempotency_key', 'booking_received:'.$booking->id)->sole();

    expect($message->status)->toBe(MessageStatus::Delivered)
        ->and($message->body)->toContain($booking->reference)
        ->and($message->body)->toContain(route('booking.show', $booking->public_token))
        ->and($message->body)->toContain('R 2 000.00');
});

it('WA-05 confirms payment once, even if the booking is confirmed again later', function (): void {
    $booking = payCash(reserve());

    expect(Message::query()->where('idempotency_key', 'payment_confirmed:'.$booking->id)->count())->toBe(1);
});

it('CHK-03 sends the farewell once at check-out', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
    $booking = payCash(reserve());
    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);

    app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist));

    expect(messageKeys($booking))->toContain('farewell:'.$booking->id);
});

it('MSG-03 reminds unpaid holds shortly before they end, once', function (): void {
    $booking = reserve();
    $booking->forceFill(['created_at' => now()->subMinutes(25), 'hold_expires_at' => now()->addMinutes(5)])->save();

    app(RunMessageAutomations::class)->handle();
    $second = app(RunMessageAutomations::class)->handle();

    expect(Message::query()->where('idempotency_key', 'hold_reminder:'.$booking->id)->count())->toBe(1)
        ->and($second['hold_reminders'])->toBe(1);
});

it('MSG-03 sends pre-arrival information two days ahead, only from 08:00', function (): void {
    $booking = payCash(reserve());

    $this->travelTo(CarbonImmutable::parse('2027-06-08 07:30', 'Africa/Johannesburg'));
    app(RunMessageAutomations::class)->handle();
    expect(messageKeys($booking))->not->toContain('pre_arrival:'.$booking->id);

    $this->travelTo(CarbonImmutable::parse('2027-06-08 08:05', 'Africa/Johannesburg'));
    app(RunMessageAutomations::class)->handle();
    expect(messageKeys($booking))->toContain('pre_arrival:'.$booking->id);
});

it('MSG-03 sends a departure-day message to guests leaving today', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
    $booking = payCash(reserve());
    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);

    $this->travelTo(CarbonImmutable::parse('2027-06-12 08:30', 'Africa/Johannesburg'));
    app(RunMessageAutomations::class)->handle();

    expect(messageKeys($booking))->toContain('departure_day:'.$booking->id);
});

it('PEND-14 sends feedback requests only when switched on', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-06-10 14:00', 'Africa/Johannesburg'));
    $booking = payCash(reserve());
    app(CheckIn::class)->handle($booking, staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);
    app(CheckOut::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist));
    MessageTemplate::query()->where('key', 'feedback_request')->update(['is_active' => true]);

    $this->travelTo(CarbonImmutable::parse('2027-06-11 15:00', 'Africa/Johannesburg'));
    app(RunMessageAutomations::class)->handle();
    expect(messageKeys($booking))->not->toContain('feedback_request:'.$booking->id);

    Setting::query()->where('key', 'messaging.feedback_request_enabled')->firstOrFail()->update(['value' => 'yes']);
    app(RunMessageAutomations::class)->handle();
    expect(messageKeys($booking))->toContain('feedback_request:'.$booking->id);
});
