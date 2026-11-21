<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceResources\Pages;

use App\Filament\Resources\ServiceResources\ServiceResourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageServiceResources extends ManageRecords
{
    protected static string $resource = ServiceResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
