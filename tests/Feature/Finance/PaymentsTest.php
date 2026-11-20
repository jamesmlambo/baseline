<?php

declare(strict_types=1);

require_once __DIR__.'/FinanceTestHelpers.php';
require_once __DIR__.'/../Bookings/BookingTestHelpers.php';

use App\Actions\Bookings\ReinstateBooking;
use App\Actions\Bookings\ReleaseExpiredHolds;
use App\Actions\Payments\VerifyEftPayment;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\StaffRole;
use App\Exceptions\ImmutableDocumentException;
use App\Exceptions\RoomUnavailableException;
use App\Models\CreditNote;
use App\Models\Receipt;
use App\Services\Finance\DocumentIssuer;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentLedger;
use App\Services\Payments\RefundWorkflow;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    seedFinanceSettings();
    Mail::fake();
});

// --- Webhooks (PAY-06) -------------------------------------------------------------

it('PAY-06 rejects webhooks with a bad signature', function (): void {
    $payment = startFakePayment(reserve());

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment, signature: str_repeat('0', 64))->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::Initiated);
});

it('PAY-06 processes a duplicated webhook only once', function (): void {
    $payment = startFakePayment(reserve());

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment, eventId: 'evt_same')->assertJson(['outcome' => 'succeeded']);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment, eventId: 'evt_same')->assertJson(['outcome' => 'duplicate']);

    expect(Receipt::query()->count())->toBe(1);
});

it('PAY-06 ignores a failure that arrives after the success', function (): void {
    $booking = reserve();
    $payment = startFakePayment($booking);

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);
    sendFakeWebhook(GatewayEvent::FAILED, $payment)->assertJson(['outcome' => 'ignored_after_success']);

    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('PAY-06 still records money when success arrives after a failure, and flags it', function (): void {
    $payment = startFakePayment(reserve());

    sendFakeWebhook(GatewayEvent::FAILED, $payment);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);

    expect($payment->fresh())
        ->status->toBe(PaymentRecordStatus::Succeeded)
        ->exception_reason->toContain('after it was reported as failed');
});

it('flags a payment when the provider reports a different amount', function (): void {
    $booking = reserve();
    $payment = startFakePayment($booking);

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment, amount: 100)->assertJson(['outcome' => 'amount_mismatch']);

    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::ReconciliationRequired)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Hold);
});

// --- Manual EFT (PAY-07) ------------------------------------------------------------

it('PAY-07 never confirms a booking on proof of EFT alone', function (): void {
    setBankDetails();
    $booking = reserve(method: PaymentMethod::Eft);

    $this->post(route('booking.eft', $booking->public_token))->assertRedirect();

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Hold)
        ->payment_status->toBe(PaymentStatus::PendingVerification);
});

it('PAY-07 confirms once a manager verifies the EFT against the bank', function (): void {
    setBankDetails();
    $booking = reserve(method: PaymentMethod::Eft);
    $payment = app(PaymentLedger::class)->recordEftNotice($booking, $booking->deposit_due_cents);
    $manager = staffUser(StaffRole::Manager);

    app(VerifyEftPayment::class)->handle($payment, $manager, 'FNB statement 12 Jun #4471', $booking->deposit_due_cents);

    expect($payment->fresh())->status->toBe(PaymentRecordStatus::Succeeded)->verified_by->toBe($manager->id)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('does not let reception verify EFT payments', function (): void {
    $booking = reserve(method: PaymentMethod::Eft);
    $payment = app(PaymentLedger::class)->recordEftNotice($booking, $booking->deposit_due_cents);

    app(VerifyEftPayment::class)->handle($payment, staffUser(StaffRole::Receptionist), 'ref');
})->throws(AuthorizationException::class);

it('refuses to verify an EFT for the wrong amount', function (): void {
    $booking = reserve(method: PaymentMethod::Eft);
    $payment = app(PaymentLedger::class)->recordEftNotice($booking, $booking->deposit_due_cents);

    app(VerifyEftPayment::class)->handle($payment, staffUser(StaffRole::Owner), 'ref', Money::cents(1_000));
})->throws(InvalidArgumentException::class);

it('records cash at reception as received immediately', function (): void {
    $booking = reserve();

    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->deposit_due_cents, staffUser(StaffRole::Receptionist));

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->receipts()->count())->toBe(1);
});

// --- Late payments (PEND-02) ---------------------------------------------------------

it('PEND-02 flags money that arrives after the hold expired, then reinstates if the room is free', function (): void {
    $booking = reserve();
    $booking->forceFill(['hold_expires_at' => now()->subMinute()])->save();
    app(ReleaseExpiredHolds::class)->handle();
    $payment = startFakePayment($booking->fresh());

    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->fresh()->exception_reason)->toContain('Reinstate');

    app(ReinstateBooking::class)->handle($booking->fresh(), staffUser(StaffRole::Manager));

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->activeBookingRooms()->count())->toBe(1)
        ->and($payment->fresh()->exception_reason)->toBeNull();
});

it('PEND-02 will not reinstate a late payment if the room has gone', function (): void {
    $type = financeRoomType();
    $booking = reserve($type);
    $booking->forceFill(['hold_expires_at' => now()->subMinute()])->save();
    app(ReleaseExpiredHolds::class)->handle();
    bookType($type, stay('2027-06-10', '2027-06-12'));

    expect(fn () => app(ReinstateBooking::class)->handle($booking->fresh(), staffUser(StaffRole::Manager)))
        ->toThrow(RoomUnavailableException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
});

// --- Refunds (DEC-06, PAY-08) --------------------------------------------------------

function paidBooking(): array
{
    $booking = reserve();
    $payment = startFakePayment($booking);
    sendFakeWebhook(GatewayEvent::SUCCEEDED, $payment);

    return [$booking->fresh(), $payment->fresh()];
}

it('DEC-06 needs a second, different person to approve a refund', function (): void {
    [, $payment] = paidBooking();
    $manager = staffUser(StaffRole::Manager);
    $workflow = app(RefundWorkflow::class);

    $refund = $workflow->request($payment, Money::cents(50_000), 'Guest left early', $manager);

    expect(fn () => $workflow->approve($refund, $manager))->toThrow(AuthorizationException::class);
    expect($refund->fresh()->status)->toBe(RefundStatus::Requested);
});

it('DEC-06 is also enforced by the database', function (): void {
    [, $payment] = paidBooking();
    $manager = staffUser(StaffRole::Manager);
    $refund = app(RefundWorkflow::class)->request($payment, Money::cents(50_000), 'Test', $manager);

    $refund->forceFill(['approved_by' => $manager->id])->save();
})->throws(QueryException::class);

it('processes an approved refund with a credit note and updates the booking', function (): void {
    [$booking, $payment] = paidBooking();
    $workflow = app(RefundWorkflow::class);

    $refund = $workflow->request($payment, $payment->amount_cents, 'Cancelled within policy', staffUser(StaffRole::Receptionist));
    $workflow->approve($refund, staffUser(StaffRole::Owner));

    expect($refund->fresh())
        ->status->toBe(RefundStatus::Processed)
        ->gateway_refund_reference->toStartWith('fake_refund_')
        ->and($payment->fresh()->refunded_cents->cents)->toBe($payment->amount_cents->cents)
        ->and(CreditNote::query()->sole()->refund_id)->toBe($refund->id)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('never refunds more than was paid', function (): void {
    [, $payment] = paidBooking();
    $workflow = app(RefundWorkflow::class);

    $workflow->request($payment, Money::cents(150_000), 'First', staffUser(StaffRole::Manager));
    $workflow->request($payment, Money::cents(100_000), 'Second', staffUser(StaffRole::Manager));
})->throws(InvalidArgumentException::class);

it('does not let reception approve refunds', function (): void {
    [, $payment] = paidBooking();
    $refund = app(RefundWorkflow::class)->request($payment, Money::cents(10_000), 'x', staffUser(StaffRole::Manager));

    app(RefundWorkflow::class)->approve($refund, staffUser(StaffRole::Receptionist));
})->throws(AuthorizationException::class);

// --- Documents (PAY-01..04) -----------------------------------------------------------

it('PAY-03 never lets an issued invoice be edited or deleted', function (): void {
    $invoice = reserve()->currentInvoice();

    expect(fn () => $invoice->update(['total_cents' => 1]))->toThrow(ImmutableDocumentException::class)
        ->and(fn () => $invoice->delete())->toThrow(ImmutableDocumentException::class)
        ->and(fn () => $invoice->lines()->first()->update(['description' => 'changed']))->toThrow(LogicException::class);
});

it('PAY-02 numbers documents sequentially per type', function (): void {
    $type = financeRoomType();
    $first = reserve($type)->currentInvoice();
    $second = reserve($type, arrival: '2027-08-01', departure: '2027-08-02', email: 'b@example.test')->currentInvoice();

    expect([$first->number, $second->number])->toBe(['INV-000001', 'INV-000002']);
});

it('PAY-04 converts a quotation into an invoice without retyping', function (): void {
    $booking = reserve();
    $issuer = app(DocumentIssuer::class);

    $quotation = $issuer->quotationForBooking($booking);
    $invoice = $issuer->convertQuotation($quotation);

    expect($invoice->quotation_id)->toBe($quotation->id)
        ->and($invoice->total_cents->equals($quotation->total_cents))->toBeTrue()
        ->and($invoice->lines->pluck('description')->all())->toBe($quotation->lines->pluck('description')->all())
        ->and($quotation->fresh()->converted_invoice_id)->toBe($invoice->id);

    expect(fn () => $issuer->convertQuotation($quotation->fresh()))->toThrow(InvalidArgumentException::class);
});

it('adds VAT only when a rate has been configured', function (): void {
    seedFinanceSettings(['payments.vat_rate_percent' => 15]);

    $invoice = reserve()->currentInvoice();

    expect($invoice->subtotal_cents->cents)->toBe(200_000)
        ->and($invoice->tax_cents->cents)->toBe(30_000)
        ->and($invoice->total_cents->cents)->toBe(230_000);
});
