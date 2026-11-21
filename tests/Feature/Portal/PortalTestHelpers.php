<?php

declare(strict_types=1);

require_once __DIR__.'/../Finance/FinanceTestHelpers.php';

use App\Actions\Reception\CheckIn;
use App\Enums\MessageChannel;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Messaging\Contracts\MessageProvider;
use App\Services\Messaging\MessageProviderManager;
use App\Services\Messaging\ProviderReceipt;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use Illuminate\Testing\TestResponse;

const PORTAL_BOOKER_PHONE = '082 555 0101';

/**
 * A paid-in-full stay arriving today, checked in to its room.
 */
function portalStay(): Booking
{
    $booking = reserve();
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));
    app(CheckIn::class)->handle($booking->fresh(), staffUser(StaffRole::Receptionist), ['full_name' => 'Lerato Demo']);

    return $booking->fresh();
}

function portalRoom(Booking $booking): Room
{
    return $booking->activeBookingRooms()->with('room')->sole()->room;
}

/**
 * Replaces the SMS provider with one that records what it was asked to send.
 *
 * @return ArrayObject<int, array{to: string, body: string}>
 */
function captureSms(): ArrayObject
{
    // Routes keep their controller (and its PortalAccess) between test requests, so bind once per test.
    if (app()->bound('test.captured_sms')) {
        return app('test.captured_sms');
    }

    $sent = new ArrayObject;
    app()->instance('test.captured_sms', $sent);

    app()->instance(MessageProviderManager::class, new class($sent) extends MessageProviderManager
    {
        public function __construct(private readonly ArrayObject $sent) {}

        public function for(MessageChannel $channel): MessageProvider
        {
            $sent = $this->sent;

            return new class($channel, $sent) implements MessageProvider
            {
                public function __construct(private readonly MessageChannel $channel, private readonly ArrayObject $sent) {}

                public function name(): string
                {
                    return 'capture';
                }

                public function channel(): MessageChannel
                {
                    return $this->channel;
                }

                public function send(string $to, string $body, ?string $providerTemplateId = null): ProviderReceipt
                {
                    $this->sent->append(['to' => $to, 'body' => $body]);

                    return new ProviderReceipt('capture_'.count($this->sent), Money::zero(), delivered: true);
                }
            };
        }
    });

    return $sent;
}

/**
 * The 6-digit portal code from the last captured SMS.
 *
 * @param  ArrayObject<int, array{to: string, body: string}>  $sent
 */
function lastPortalCode(ArrayObject $sent): string
{
    $last = $sent[count($sent) - 1] ?? null;
    expect($last)->not->toBeNull();
    preg_match('/code is (\d{6})/', $last['body'], $matches);

    return $matches[1];
}

/**
 * Signs the test client in through the room QR: phone number, then the code.
 */
function signInToPortal(Room $room, string $phone = PORTAL_BOOKER_PHONE): TestResponse
{
    $sent = captureSms();
    $token = $room->ensurePortalToken();

    test()->post(route('portal.room.code', $token), ['phone' => $phone])->assertRedirect(route('portal.room.verify', $token));

    return test()->post(route('portal.room.verify.submit', $token), ['code' => lastPortalCode($sent)]);
}
