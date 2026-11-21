<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyTasks\Pages;

use App\Filament\Actions\HousekeepingActions;
use App\Filament\Staff\Resources\MyTasks\MyTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListMyTasks extends ListRecords
{
    protected static string $resource = MyTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [HousekeepingActions::reportFault()];
    }
}
