<?php

declare(strict_types=1);

namespace App\Filament\Resources\Amenities\Pages;

use App\Filament\Resources\Amenities\AmenityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAmenity extends CreateRecord
{
    protected static string $resource = AmenityResource::class;
}
