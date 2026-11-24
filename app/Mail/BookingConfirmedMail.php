<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\Finance\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Sent once a booking is confirmed, with the invoice(s) and receipt(s) attached (WA-05, WA-06).
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

        return $this->documents()
            ->map(fn ($document) => Attachment::fromData(fn (): string => $pdf->render($document), $pdf->filename($document))->withMime('application/pdf'))
            ->values()
            ->all();
    }

    /**
     * The invoices and receipts attached to this email.
     *
     * @return Collection<int, Invoice|Receipt>
     */
    public function documents(): Collection
    {
        return $this->booking->invoices()->whereNull('voided_at')->get()
            ->toBase()
            ->concat($this->booking->receipts()->get());
    }
}
