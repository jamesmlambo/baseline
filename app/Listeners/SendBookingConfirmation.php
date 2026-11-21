<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use App\Services\Messaging\BookingMessages;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the guest their booking is confirmed (MSG-03, WA-05): email with receipt attached,
 * plus a WhatsApp/SMS message with the booking link. A failed email never blocks the message.
 */
class SendBookingConfirmation
{
    public function __construct(private readonly BookingMessages $messages) {}

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;
        $email = $booking->lead_guest_email;

        if ($email !== null && $email !== '') {
            try {
                Mail::to($email)->send(new BookingConfirmedMail($booking));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->messages->paymentConfirmed($booking);
    }
}
