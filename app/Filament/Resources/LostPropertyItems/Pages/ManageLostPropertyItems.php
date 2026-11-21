<?php

declare(strict_types=1);

namespace App\Filament\Resources\LostPropertyItems\Pages;

use App\Filament\Resources\LostPropertyItems\LostPropertyItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLostPropertyItems extends ManageRecords
{
    protected static string $resource = LostPropertyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => $data + ['found_by' => auth()->id()]),
        ];
    }
}
