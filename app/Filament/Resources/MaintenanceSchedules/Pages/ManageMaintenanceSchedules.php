<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaintenanceSchedules\Pages;

use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenanceSchedules extends ManageRecords
{
    protected static string $resource = MaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
