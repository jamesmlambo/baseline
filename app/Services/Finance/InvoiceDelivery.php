<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Mail\InvoiceMail;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Sends invoices to guests from the Invoices section: by email (PDF attached) or by WhatsApp
 * (click-to-chat with the invoice link, so it works before a WhatsApp provider is approved).
 */
class InvoiceDelivery
{
    public const MAX_MESSAGE_LENGTH = 2000;

    /**
     * @param  string|null  $message  optional note from staff, shown at the top of the email
     */
    public function email(Invoice $invoice, string $email, ?User $by = null, ?string $message = null): void
    {
        $email = trim($email);
        $message = trim((string) $message);

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException('Keep the message under '.self::MAX_MESSAGE_LENGTH.' characters.');
        }

        Mail::to($email)->send(new InvoiceMail($invoice, $message !== '' ? $message : null));
        $invoice->recordEmail($email, by: $by);

        activity('finance')
            ->performedOn($invoice)
            ->causedBy($by)
            ->log("Invoice {$invoice->number} emailed ({$invoice->statusLabel()})");
    }

    /**
     * A wa.me link that opens WhatsApp with the message ready to send. No number: staff choose the chat.
     */
    public function whatsAppUrl(Invoice $invoice, ?string $phone = null): string
    {
        $number = ltrim((string) Guest::normalisePhone($phone), '+');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->whatsAppText($invoice));
    }

    public function whatsAppText(Invoice $invoice): string
    {
        $name = strtok($invoice->recipient_name, ' ') ?: 'there';

        $status = $invoice->statusLabel() === 'PAID'
            ? sprintf('It is paid (%s). Thank you!', $invoice->total_cents->format())
            : sprintf('Amount to pay: %s.', Money::cents(max(0, $invoice->balanceDue()->cents))->format());

        return sprintf(
            'Hi %s, here is your Baseline Lodge invoice %s%s: %s %s We will never ask for card details by WhatsApp.',
            $name,
            $invoice->number,
            $invoice->booking ? ' for booking '.$invoice->booking->reference : '',
            $invoice->publicUrl(),
            $status,
        );
    }
}
