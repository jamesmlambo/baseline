<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Finance\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An invoice sent by staff from the Invoices section, with the PDF attached (PAY-02).
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $note  optional message from staff, shown above the invoice details
     */
    public function __construct(public readonly Invoice $invoice, public readonly ?string $note = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invoice {$this->invoice->number} ({$this->invoice->statusLabel()}), Baseline Lodge");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'invoice' => $this->invoice,
            'note' => $this->note,
            'isPaid' => $this->invoice->statusLabel() === 'PAID',
            'balance' => $this->invoice->balanceDue(),
            'url' => $this->invoice->publicUrl(),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(DocumentPdf::class);

        return [
            Attachment::fromData(fn (): string => $pdf->render($this->invoice), $pdf->filename($this->invoice))->withMime('application/pdf'),
        ];
    }
}
