<?php

declare(strict_types=1);

namespace App\Filament\Resources\RosterPatterns\Pages;

use App\Filament\Resources\RosterPatterns\RosterPatternResource;
use App\Models\RosterPattern;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageRosterPatterns extends ManageRecords
{
    protected static string $resource = RosterPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add to roster')
                ->using(fn (array $data): RosterPattern => DB::transaction(function () use ($data): RosterPattern {
                    $created = null;

                    foreach ($data['weekdays'] as $weekday) {
                        $created = RosterPattern::query()->create([
                            'employee_id' => $data['employee_id'],
                            'shift_id' => $data['shift_id'],
                            'weekday' => (int) $weekday,
                            'effective_from' => $data['effective_from'],
                            'effective_until' => $data['effective_until'] ?? null,
                            'works_public_holidays' => (bool) ($data['works_public_holidays'] ?? true),
                        ]);
                    }

                    return $created;
                })),
        ];
    }
}
