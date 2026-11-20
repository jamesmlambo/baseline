<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Tables;

use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccommodationTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->badge(fn ($record): bool => $record->is_demo)
                    ->color(fn ($record): ?string => $record->is_demo ? 'warning' : null)
                    ->description(fn ($record): ?string => $record->is_demo ? 'DEMO' : null),
                TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->counts('rooms'),
                TextColumn::make('max_occupancy')
                    ->label('Sleeps'),
                TextColumn::make('base_nightly_rate_cents')
                    ->label('Base rate')
                    ->formatStateUsing(fn (?Money $state): string => $state?->format() ?? '—'),
                IconColumn::make('is_active')
                    ->label('Bookable')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Bookable'),
                TernaryFilter::make('is_demo')->label('Demo data'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
