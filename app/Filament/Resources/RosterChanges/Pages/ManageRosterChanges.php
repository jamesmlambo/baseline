<?php

declare(strict_types=1);

namespace App\Filament\Resources\RosterChanges\Pages;

use App\Filament\Resources\RosterChanges\RosterChangeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRosterChanges extends ManageRecords
{
    protected static string $resource = RosterChangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
        ];
    }
}
