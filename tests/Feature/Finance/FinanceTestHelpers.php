<?php

declare(strict_types=1);

use App\Actions\Bookings\CreateReservation;
use App\Actions\Bookings\Data\ReservationRequest;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ExtraPricingUnit;
use App\Enums\PaymentMethod;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Extra;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use App\Support\StayDates;
use Database\Seeders\SettingSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Testing\TestResponse;

function seedFinanceSettings(array $overrides = []): void
{
    test()->seed([SettingSeeder::class, WebsiteContentSeeder::class]);

    foreach ($overrides as $key => $value) {
        Setting::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);
    }
}

function setBankDetails(): void
{
    seedFinanceSettings([
        'payments.bank_name' => 'Demo Bank',
        'payments.bank_account_name' => 'Baseline Lodge (DEMO)',
        'payments.bank_account_number' => '0000000000',
        'payments.bank_branch_code' => '000000',
    ]);
}

function breakfast(): Extra
{
    return Extra::query()->create([
        'name' => 'Breakfast',
        'pricing_unit' => ExtraPricingUnit::PerGuestPerNight,
        'price_cents' => 15_000,
        'is_active' => true,
        'is_bookable_online' => true,
    ]);
}

/**
 * A room type at R1 000 a night with one room.
 */
function financeRoomType(): AccommodationType
{
    return AccommodationType::factory()->withRooms(1)->create([
        'name' => 'Garden Room',
        'base_nightly_rate_cents' => 100_000,
        'base_occupancy' => 2,
        'max_occupancy' => 2,
    ]);
}

function reserve(?AccommodationType $type = null, array $extras = [], PaymentMethod $method = PaymentMethod::InstantEft, string $email = 'guest@example.test', string $arrival = '2027-06-10', string $departure = '2027-06-12'): Booking
{
    return app(CreateReservation::class)->handle(new ReservationRequest(
        type: $type ?? financeRoomType(),
        stay: new StayDates($arrival, $departure),
        adults: 2,
        children: 0,
        firstName: 'Lerato',
        lastName: 'Demo',
        email: $email,
        phone: '082 555 0101',
        source: BookingSource::Website,
        status: BookingStatus::Hold,
        preferredPaymentMethod: $method,
        extraQuantities: $extras,
        termsAccepted: true,
    ));
}

function startFakePayment(Booking $booking, ?Money $amount = null, PaymentMethod $method = PaymentMethod::InstantEft): Payment
{
    return app(PaymentLedger::class)->startOnlinePayment(
        $booking,
        $method,
        $amount ?? $booking->deposit_due_cents,
        'fake',
        'fake_'.str()->random(20),
    );
}

function sendFakeWebhook(string $type, Payment $payment, ?int $amount = null, ?string $eventId = null, ?string $signature = null): TestResponse
{
    $webhook = app(FakeGateway::class)->makeWebhook($type, $payment->gateway_reference, $amount ?? $payment->amount_cents->cents, $eventId);

    return test()->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X_FAKE_SIGNATURE' => $signature ?? $webhook['signature'],
        'CONTENT_TYPE' => 'application/json',
    ], $webhook['body']);
}
