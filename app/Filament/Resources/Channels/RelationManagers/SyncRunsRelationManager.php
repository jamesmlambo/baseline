<?php

declare(strict_types=1);

namespace App\Filament\Resources\Channels\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * CHN-04: every sync attempt and its result, read-only.
 */
class SyncRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncRuns';

    protected static ?string $title = 'Sync log';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('When')->dateTime('j M H:i', 'Africa/Johannesburg'),
                TextColumn::make('operation')->formatStateUsing(fn (string $state): string => $state === 'ical_import' ? 'iCal import' : 'Reservations'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ok' => 'OK',
                        'not_connected' => 'Not connected',
                        default => 'Failed',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'not_connected' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('items'),
                TextColumn::make('message')->wrap()->placeholder('—'),
            ]);
    }
}
