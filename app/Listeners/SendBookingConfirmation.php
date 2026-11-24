<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MessageChannel;
use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use App\Models\DocumentDelivery;
use App\Services\Messaging\BookingMessages;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the guest their booking is confirmed (MSG-03, WA-05): email with the invoice (stamped PAID)
 * and receipt attached, plus an SMS with the booking link. A failed email never blocks the SMS.
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
                $mail = new BookingConfirmedMail($booking);
                Mail::to($email)->send($mail);

                foreach ($mail->documents() as $document) {
                    $document->recordEmail($email, DocumentDelivery::REASON_BOOKING_CONFIRMED);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        // The client wants the "booking successful" message by SMS, whatever the preferred channel.
        $this->messages->paymentConfirmed($booking, MessageChannel::Sms);
    }
}
