<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Pages;

use App\Filament\Resources\AccommodationTypes\AccommodationTypeResource;
use App\Filament\Resources\AccommodationTypes\Pages\Concerns\SavesRoomCount;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccommodationType extends CreateRecord
{
    use SavesRoomCount;

    protected static string $resource = AccommodationTypeResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveWithRoomCount($data, fn (array $data): Model => parent::handleRecordCreation($data));
    }
}
