<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Pages;

use App\Filament\Resources\AccommodationTypes\AccommodationTypeResource;
use App\Filament\Resources\AccommodationTypes\Pages\Concerns\SavesRoomCount;
use App\Models\AccommodationType;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAccommodationType extends EditRecord
{
    use SavesRoomCount;

    protected static string $resource = AccommodationTypeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var AccommodationType $type */
        $type = $this->getRecord();
        $data['room_count'] = $type->rooms()->active()->count();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveWithRoomCount($data, fn (array $data): Model => parent::handleRecordUpdate($record, $data));
    }

    protected function afterSave(): void
    {
        $this->dispatch('room-count-changed');
    }
}
