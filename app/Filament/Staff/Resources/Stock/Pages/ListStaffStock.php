<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Stock\Pages;

use App\Filament\Staff\Resources\Stock\StaffStockResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffStock extends ListRecords
{
    protected static string $resource = StaffStockResource::class;
}
