<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('group')->titlePrefixedWithLabel(false))
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->description(fn ($record): string => $record->key),
                TextColumn::make('value')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(' → ', $state) : (string) $state)
                    ->placeholder('Not set'),
                IconColumn::make('is_pending_client_decision')
                    ->label('Pending')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                TextColumn::make('decision_reference')
                    ->label('Ref')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('is_pending_client_decision')
                    ->label('Awaiting client decision'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
