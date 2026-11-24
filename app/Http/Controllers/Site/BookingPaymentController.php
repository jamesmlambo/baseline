<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paying for a website booking: hand-off to the payment provider, or an EFT notice.
 */
class BookingPaymentController extends Controller
{
    public function pay(Request $request, string $token, PaymentGatewayManager $gateways, PaymentLedger $ledger): RedirectResponse
    {
        $booking = BookingController::findByToken($token);

        $request->validate([
            'method' => ['required', Rule::in([PaymentMethod::InstantEft->value, PaymentMethod::Card->value])],
        ]);

        if (! $this->canTakePayment($booking)) {
            return redirect()->route('booking.show', $token)->with('status', 'This booking cannot be paid online any more. Please contact us.');
        }

        if ($booking->isBankTransferOnly()) {
            return redirect()->route('booking.show', $token)->with('status', 'This booking is paid by bank transfer. The banking details are below.');
        }

        $amount = $this->amountToPay($booking);

        if ($amount->cents <= 0) {
            return redirect()->route('booking.show', $token);
        }

        $gateway = $gateways->default();
        $method = PaymentMethod::from($request->string('method')->value());

        $session = $gateway->createCheckout(
            $booking->payments()->make(['amount_cents' => $amount, 'method' => $method]),
            route('booking.return', $token),
            route('booking.show', $token),
        );

        $ledger->startOnlinePayment($booking, $method, $amount, $gateway->name(), $session->gatewayReference);

        return redirect()->away($session->redirectUrl);
    }

    public function eftNotice(Request $request, string $token, PaymentLedger $ledger): RedirectResponse
    {
        $booking = BookingController::findByToken($token);

        $request->validate([
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->canTakePayment($booking)) {
            return redirect()->route('booking.show', $token)->with('status', 'Please contact us about this booking.');
        }

        $alreadyNotified = $booking->payments()->where('status', PaymentRecordStatus::PendingVerification)->exists();

        if (! $alreadyNotified) {
            $payment = $ledger->recordEftNotice($booking, $this->amountToPay($booking), $request->input('notes'));

            if ($request->hasFile('proof')) {
                $payment->addMediaFromRequest('proof')->toMediaCollection('proof');
            }
        }

        return redirect()->route('booking.show', $token)->with(
            'status',
            'Thank you. We will confirm your booking as soon as the payment reaches our bank account.',
        );
    }

    /**
     * Where the provider sends the guest back. Rendered directly (not redirected) so the
     * provider's flash message survives. The webhook, not this page, decides whether money arrived.
     */
    public function return(string $token, BookingController $bookings): View
    {
        return $bookings->show($token);
    }

    private function canTakePayment(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed], true)
            && ! ($booking->status === BookingStatus::Hold && $booking->hold_expires_at?->isPast());
    }

    private function amountToPay(Booking $booking): Money
    {
        $balance = $booking->balanceDue();

        // Before confirmation only the deposit is required; afterwards, whatever is still owed.
        $target = $booking->status === BookingStatus::Confirmed
            ? $balance
            : Money::cents(min($booking->deposit_due_cents->cents - $booking->paidTotal()->cents, $balance->cents));

        return Money::cents(max(0, $target->cents));
    }
}
