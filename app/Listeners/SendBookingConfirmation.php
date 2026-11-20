<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the guest when their booking is confirmed (MSG-03 payment confirmation).
 * WhatsApp and SMS versions arrive with Milestone 6.
 */
class SendBookingConfirmation
{
    public function handle(BookingConfirmed $event): void
    {
        $email = $event->booking->lead_guest_email;

        if ($email !== null && $email !== '') {
            Mail::to($email)->send(new BookingConfirmedMail($event->booking));
        }
    }
}
