<?php

declare(strict_types=1);

require_once __DIR__.'/FinanceTestHelpers.php';

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Services\Payments\GatewayEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    seedFinanceSettings();
    RateLimiter::clear('enquiries');
    Mail::fake();
});

function bookingForm(int $typeId, array $overrides = []): array
{
    return [
        'accommodation_type_id' => $typeId,
        'arrival' => '2027-06-10',
        'departure' => '2027-06-12',
        'adults' => 2,
        'children' => 0,
        'first_name' => 'Lerato',
        'last_name' => 'Demo',
        'email' => 'Lerato@Example.test',
        'phone' => '082 555 0101',
        'payment_method' => 'instant_eft',
        'accept_terms' => '1',
        ...$overrides,
    ];
}

it('OBK-02 offers Instant EFT first on the booking page', function (): void {
    $type = financeRoomType();

    $this->get(route('book', ['type' => $type->id, 'arrival' => '2027-06-10', 'departure' => '2027-06-12', 'adults' => 2]))
        ->assertOk()
        ->assertSeeInOrder(['Instant EFT', 'Recommended', 'Card', 'EFT (bank transfer)'])
        ->assertSee('Not available yet.');
});

it('creates a held booking with guest profile, extras and invoice, and emails the guest', function (): void {
    $type = financeRoomType();
    $extra = breakfast();

    $response = $this->post('/book', bookingForm($type->id, ['extras' => [$extra->id]]));

    $booking = Booking::query()->sole();
    $response->assertRedirect(route('booking.show', $booking->public_token));

    // R2 000 room + breakfast 2 guests x 2 nights x R150 = R2 600
    expect($booking->status)->toBe(BookingStatus::Hold)
        ->and($booking->hold_expires_at->isFuture())->toBeTrue()
        ->and($booking->total_cents->cents)->toBe(260_000)
        ->and($booking->deposit_due_cents->cents)->toBe(260_000)
        ->and($booking->terms_accepted_at)->not->toBeNull()
        ->and($booking->activeBookingRooms)->toHaveCount(1)
        ->and($booking->guest->fullName())->toBe('Lerato Demo')
        ->and($booking->currentInvoice()->total_cents->cents)->toBe(260_000)
        ->and($booking->currentInvoice()->lines)->toHaveCount(2)
        ->and($booking->currentInvoice()->number)->toBe('INV-000001');

    Mail::assertSent(BookingReceived::class, fn (BookingReceived $mail): bool => $mail->hasTo('lerato@example.test'));
});

it('refuses EFT while no banking details are configured', function (): void {
    $type = financeRoomType();

    $this->post('/book', bookingForm($type->id, ['payment_method' => 'eft']))
        ->assertSessionHasErrors('payment_method');

    expect(Booking::query()->count())->toBe(0);
});

it('sends the guest back to search when the last room has just gone', function (): void {
    $type = financeRoomType();
    reserve($type);

    $this->post('/book', bookingForm($type->id, ['email' => 'second@example.test']))
        ->assertRedirect()
        ->assertSessionHasErrors('arrival');

    expect(Booking::query()->count())->toBe(1);
});

it('pays online end to end through the fake provider and confirms the booking', function (): void {
    $type = financeRoomType();
    $this->post('/book', bookingForm($type->id));
    $booking = Booking::query()->sole();

    $this->get(route('booking.show', $booking->public_token))
        ->assertOk()
        ->assertSee('please pay to confirm')
        ->assertSee('Pay with Instant EFT');

    $redirect = $this->post(route('booking.pay', $booking->public_token), ['method' => 'instant_eft']);
    $payment = Payment::query()->sole();
    $redirect->assertRedirect();
    expect($redirect->headers->get('Location'))->toContain('/dev/fake-gateway');

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment)->assertOk()->assertJson(['outcome' => 'succeeded']);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_status)->toBe(PaymentStatus::VerifiedPaid)
        ->and($payment->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($booking->receipts)->toHaveCount(1)
        ->and($booking->statusHistory->last()->reason)->toBe('Payment received');

    Mail::assertSent(BookingConfirmedMail::class);

    $this->get(route('booking.show', $booking->public_token))->assertSee('Your booking is confirmed');
});

it('serves invoice and receipt PDFs only by their own secret link', function (): void {
    $booking = reserve();
    $payment = startFakePayment($booking);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);
    $invoice = $booking->currentInvoice();
    $receipt = $booking->receipts()->sole();

    $this->get(route('documents.show', ['invoice', $invoice->public_token]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('documents.show', ['receipt', $receipt->public_token]))->assertOk();
    $this->get(route('documents.show', ['invoice', str_repeat('a', 48)]))->assertNotFound();
});

it('BOOK-06 reuses a returning guest and keeps contact details encrypted', function (): void {
    $type = financeRoomType();
    reserve($type, email: 'Lerato@Example.test');
    reserve($type, email: ' lerato@example.test ', arrival: '2027-07-01', departure: '2027-07-03');

    expect(Guest::query()->count())->toBe(1)
        ->and(Guest::query()->sole()->bookings()->count())->toBe(2);

    $raw = DB::table('guests')->first();
    expect($raw->email)->not->toContain('lerato')
        ->and($raw->phone)->not->toContain('555')
        ->and(Guest::query()->sole()->phone)->toBe('+27825550101');
});

it('records marketing consent only when the guest ticks it', function (): void {
    $type = financeRoomType();
    $this->post('/book', bookingForm($type->id, ['marketing_consent' => '1']));

    $guest = Guest::query()->sole();
    expect($guest->marketing_consent)->toBeTrue()
        ->and($guest->marketing_consent_at)->not->toBeNull();
});

it('takes a part payment as the deposit when configured', function (): void {
    seedFinanceSettings(['payments.deposit_percent' => 50]);
    $booking = reserve();

    expect($booking->deposit_due_cents->cents)->toBe(100_000);

    $payment = startFakePayment($booking);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Confirmed)
        ->payment_status->toBe(PaymentStatus::PartPaid);
});
