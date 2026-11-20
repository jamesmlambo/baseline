<?php

declare(strict_types=1);

namespace App\Filament\Resources\Guests\Pages;

use App\Filament\Resources\Guests\GuestResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Guest profiles are created with their first booking, never on their own.
 */
class ListGuests extends ListRecords
{
    protected static string $resource = GuestResource::class;
}
