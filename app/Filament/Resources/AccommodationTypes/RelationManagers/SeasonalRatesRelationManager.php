<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\RelationManagers;

use App\Filament\Forms\MoneyInput;
use App\Models\SeasonalRate;
use App\Support\Money;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Seasonal nightly rates. Date ranges are inclusive and may not overlap (also enforced in PostgreSQL).
 */
class SeasonalRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'seasonalRates';

    protected static ?string $title = 'Seasonal rates';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Festive season'),
                MoneyInput::make('nightly_rate_cents')
                    ->label('Nightly rate')
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('First night')
                    ->required()
                    ->native(false),
                DatePicker::make('ends_on')
                    ->label('Last night')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('starts_on')
                    ->rule(fn (Get $get, ?SeasonalRate $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $overlaps = $this->getOwnerRecord()->seasonalRates()
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->where('starts_on', '<=', $value)
                            ->where('ends_on', '>=', $get('starts_on'))
                            ->exists();

                        if ($overlaps) {
                            $fail('These dates overlap another season for this room type.');
                        }
                    }),
                TextInput::make('min_nights')
                    ->label('Minimum nights')
                    ->integer()
                    ->minValue(1)
                    ->helperText('Optional. Shown to staff now; enforced in the booking flow from Milestone 4.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('starts_on')
                    ->label('First night')
                    ->date('j M Y'),
                TextColumn::make('ends_on')
                    ->label('Last night')
                    ->date('j M Y'),
                TextColumn::make('nightly_rate_cents')
                    ->label('Nightly rate')
                    ->formatStateUsing(fn (?Money $state): string => $state?->format() ?? '—'),
                TextColumn::make('min_nights')
                    ->label('Min nights')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
