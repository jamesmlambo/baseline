<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Address')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->color('gray'),
                TextColumn::make('key')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Built-in' : 'Custom')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'gray' : 'info'),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->label('Demo')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_demo')->label('Demo content'),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record): string => route('page', $record->slug))
                    ->openUrlInNewTab()
                    ->visible(fn (Page $record): bool => $record->is_published),
                EditAction::make(),
            ]);
    }
}
