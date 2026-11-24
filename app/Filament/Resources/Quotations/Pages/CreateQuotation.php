<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Services\Finance\DocumentIssuer;
use App\Services\Finance\QuotationDelivery;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $quotation = app(DocumentIssuer::class)->quotationForCustomer(
                ['name' => (string) $data['recipient_name'], 'email' => $data['recipient_email'] ?? null, 'phone' => $data['recipient_phone'] ?? null],
                QuotationResource::buildLines($data),
                (int) $data['valid_days'],
                $data['notes'] ?? null,
                auth()->user(),
                QuotationResource::items($data),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Quotation not created')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }

        if ($data['send_email'] ?? false) {
            $this->emailToCustomer($quotation, $data['message'] ?? null);
        }

        return $quotation;
    }

    private function emailToCustomer(Quotation $quotation, ?string $message): void
    {
        if (blank($quotation->recipient_email)) {
            Notification::make()->title('Not emailed')->body('No email address was given. Use Email on the quotation to send it.')->warning()->send();

            return;
        }

        try {
            app(QuotationDelivery::class)->email($quotation, $quotation->recipient_email, auth()->user(), $message);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Not emailed')->body($exception->getMessage())->warning()->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Quotation created';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
