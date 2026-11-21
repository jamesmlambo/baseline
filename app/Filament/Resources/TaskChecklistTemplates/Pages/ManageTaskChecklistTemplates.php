<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaskChecklistTemplates\Pages;

use App\Filament\Resources\TaskChecklistTemplates\TaskChecklistTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaskChecklistTemplates extends ManageRecords
{
    protected static string $resource = TaskChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
