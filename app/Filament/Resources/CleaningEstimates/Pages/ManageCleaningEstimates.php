<?php

declare(strict_types=1);

namespace App\Filament\Resources\CleaningEstimates\Pages;

use App\Filament\Resources\CleaningEstimates\CleaningEstimateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCleaningEstimates extends ManageRecords
{
    protected static string $resource = CleaningEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
