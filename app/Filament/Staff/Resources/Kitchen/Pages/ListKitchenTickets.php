<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Kitchen\Pages;

use App\Filament\Staff\Resources\Kitchen\KitchenTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListKitchenTickets extends ListRecords
{
    protected static string $resource = KitchenTicketResource::class;
}
