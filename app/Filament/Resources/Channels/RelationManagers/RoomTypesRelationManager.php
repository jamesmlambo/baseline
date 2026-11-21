<?php

declare(strict_types=1);

namespace App\Filament\Resources\Channels\RelationManagers;

use App\Models\AccommodationType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Which of our room types each channel room code means (CHN-03).
 */
class RoomTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'roomTypes';

    protected static ?string $title = 'Room codes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('external_code')->label('Code on the channel')->required()->maxLength(64),
            Select::make('accommodation_type_id')->label('Our room type')->options(fn (): array => AccommodationType::query()->orderBy('sort_order')->pluck('name', 'id')->all())->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_code')->label('Code on the channel'),
                TextColumn::make('accommodationType.name')->label('Our room type'),
            ])
            ->headerActions([CreateAction::make()->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))])
            ->recordActions([
                EditAction::make()->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord())),
                DeleteAction::make()->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord())),
            ]);
    }
}
