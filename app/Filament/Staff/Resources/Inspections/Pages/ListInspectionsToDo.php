<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Inspections\Pages;

use App\Filament\Staff\Resources\Inspections\InspectionToDoResource;
use Filament\Resources\Pages\ListRecords;

class ListInspectionsToDo extends ListRecords
{
    protected static string $resource = InspectionToDoResource::class;
}
