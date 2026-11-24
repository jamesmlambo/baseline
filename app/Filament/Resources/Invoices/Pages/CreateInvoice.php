<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Payments\IssueBookingInvoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\Finance\InvoiceDelivery;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $booking = Booking::query()->findOrFail($data['booking_id']);
        $unitPrice = $data['unit_price'] ?? null;

        try {
            $invoice = app(IssueBookingInvoice::class)->handle(
                $booking,
                (string) $data['kind'],
                auth()->user(),
                $data['description'] ?? null,
                (int) ($data['quantity'] ?? 1),
                $unitPrice instanceof Money ? $unitPrice : null,
            );
        } catch (InvalidArgumentException|AuthorizationException $exception) {
            Notification::make()->title('Invoice not created')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }

        if ($data['send_email'] ?? false) {
            $this->emailToGuest($invoice, $booking, $data['message'] ?? null);
        }

        return $invoice;
    }

    private function emailToGuest(Invoice $invoice, Booking $booking, ?string $message): void
    {
        if (blank($booking->lead_guest_email)) {
            Notification::make()->title('Not emailed')->body('The booking has no email address. Use Email on the invoice to send it to an address.')->warning()->send();

            return;
        }

        try {
            app(InvoiceDelivery::class)->email($invoice, $booking->lead_guest_email, auth()->user(), $message);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Not emailed')->body($exception->getMessage())->warning()->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invoice created';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
