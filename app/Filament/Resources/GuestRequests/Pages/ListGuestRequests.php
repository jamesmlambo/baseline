<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestRequests\Pages;

use App\Filament\Resources\GuestRequests\GuestRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListGuestRequests extends ListRecords
{
    protected static string $resource = GuestRequestResource::class;
}
