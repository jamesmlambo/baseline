<?php

declare(strict_types=1);

namespace App\Filament\Resources\PriceLists\Pages;

use App\Actions\Messaging\CreatePriceList;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Support\StayDates;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreatePriceListPage extends CreateRecord
{
    protected static string $resource = PriceListResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreatePriceList::class)->handle(
                new StayDates($data['arrival'], $data['departure']),
                (int) $data['adults'],
                (int) ($data['children'] ?? 0),
                auth()->user(),
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
                (bool) ($data['send'] ?? false),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('Price list not created')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Price list created';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
