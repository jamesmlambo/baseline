<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Pages;

use App\Filament\Resources\AccommodationTypes\AccommodationTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditAccommodationType extends EditRecord
{
    protected static string $resource = AccommodationTypeResource::class;
}
