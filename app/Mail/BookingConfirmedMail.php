<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Services\Finance\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a booking is confirmed, with the receipt(s) attached (WA-05, WA-06).
 */
class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Booking confirmed — {$this->booking->reference}, Baseline Lodge");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-confirmed', with: [
            'booking' => $this->booking,
            'statusUrl' => $this->booking->public_token ? route('booking.show', $this->booking->public_token) : null,
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(DocumentPdf::class);

        return $this->booking->receipts()->get()
            ->map(fn ($receipt) => Attachment::fromData(fn (): string => $pdf->render($receipt), $pdf->filename($receipt))->withMime('application/pdf'))
            ->values()
            ->all();
    }
}
