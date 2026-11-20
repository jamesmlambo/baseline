<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Physical rooms of a type. Rooms are deactivated, never deleted.
 */
class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Short internal code, e.g. R01.'),
                Textarea::make('internal_notes')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->integer()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Bookable')
                    ->default(true),
                Toggle::make('is_demo')
                    ->label('Demo data')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Bookable')
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->label('Demo')
                    ->boolean()
                    ->trueColor('warning'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
