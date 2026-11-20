<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\FinanceSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a booking is created and not yet paid: the unpaid invoice (PDF) and how to pay (WA-04, WA-06).
 */
class BookingReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Baseline Lodge booking {$this->booking->reference} — payment needed");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-received', with: [
            'booking' => $this->booking,
            'bank' => app(FinanceSettings::class)->bankDetails(),
            'statusUrl' => route('booking.show', $this->booking->public_token),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $invoice = $this->booking->currentInvoice();

        if ($invoice === null) {
            return [];
        }

        $pdf = app(DocumentPdf::class);

        return [Attachment::fromData(fn (): string => $pdf->render($invoice), $pdf->filename($invoice))->withMime('application/pdf')];
    }
}
