<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Quotation;
use App\Services\Finance\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A quotation sent by staff from the Quotations section, with the PDF attached (PAY-01).
 */
class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $note  optional message from staff, shown above the quotation details
     */
    public function __construct(public readonly Quotation $quotation, public readonly ?string $note = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Quotation {$this->quotation->number}, Baseline Lodge");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.quotation', with: [
            'quotation' => $this->quotation,
            'note' => $this->note,
            'url' => $this->quotation->publicUrl(),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(DocumentPdf::class);

        return [
            Attachment::fromData(fn (): string => $pdf->render($this->quotation), $pdf->filename($this->quotation))->withMime('application/pdf'),
        ];
    }
}
