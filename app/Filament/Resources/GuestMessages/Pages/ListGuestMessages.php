<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestMessages\Pages;

use App\Filament\Resources\GuestMessages\GuestMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListGuestMessages extends ListRecords
{
    protected static string $resource = GuestMessageResource::class;
}
