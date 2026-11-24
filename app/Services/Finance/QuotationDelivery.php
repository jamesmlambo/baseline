<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Mail\QuotationMail;
use App\Models\Guest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Sends quotations from the Quotations section: by email (PDF attached, optional staff message)
 * or by WhatsApp click-to-chat with the quotation link, as invoices are sent.
 */
class QuotationDelivery
{
    public function email(Quotation $quotation, string $email, ?User $by = null, ?string $message = null): void
    {
        $email = trim($email);
        $message = trim((string) $message);

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        if (mb_strlen($message) > InvoiceDelivery::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException('Keep the message under '.InvoiceDelivery::MAX_MESSAGE_LENGTH.' characters.');
        }

        Mail::to($email)->send(new QuotationMail($quotation, $message !== '' ? $message : null));
        $quotation->recordEmail($email, by: $by);

        activity('finance')
            ->performedOn($quotation)
            ->causedBy($by)
            ->log("Quotation {$quotation->number} emailed");
    }

    /**
     * A wa.me link that opens WhatsApp with the message ready to send. No number: staff choose the chat.
     */
    public function whatsAppUrl(Quotation $quotation, ?string $phone = null): string
    {
        $number = ltrim((string) Guest::normalisePhone($phone), '+');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->whatsAppText($quotation));
    }

    public function whatsAppText(Quotation $quotation): string
    {
        return sprintf(
            'Hi %s, here is your Baseline Lodge quotation %s for %s%s: %s Rooms and services are only reserved once you book. We will never ask for card details by WhatsApp.',
            strtok($quotation->recipient_name, ' ') ?: 'there',
            $quotation->number,
            $quotation->total_cents->format(),
            $quotation->valid_until ? ', valid until '.$quotation->valid_until->format('j M Y') : '',
            $quotation->publicUrl(),
        );
    }
}
