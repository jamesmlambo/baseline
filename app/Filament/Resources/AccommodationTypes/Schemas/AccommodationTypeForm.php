<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes\Schemas;

use App\Filament\Forms\MoneyInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AccommodationTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room type')
                    ->description('Guests book a room type. A specific room is assigned automatically at booking.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in the website address.'),
                        Textarea::make('summary')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
                Section::make('Occupancy and pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('base_occupancy')
                            ->label('Guests included in the rate')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->default(2),
                        TextInput::make('max_occupancy')
                            ->label('Maximum guests')
                            ->integer()
                            ->minValue(1)
                            ->gte('base_occupancy')
                            ->required()
                            ->default(2),
                        MoneyInput::make('base_nightly_rate_cents')
                            ->label('Base nightly rate')
                            ->required()
                            ->helperText('Used on nights without a seasonal rate.'),
                        MoneyInput::make('extra_guest_nightly_cents')
                            ->label('Extra guest per night')
                            ->default('0.00')
                            ->required(),
                    ]),
                Section::make('Visibility')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Bookable')
                            ->default(true),
                        Toggle::make('is_demo')
                            ->label('Demo data')
                            ->helperText('Must be off for real rooms before launch.')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->integer()
                            ->default(0),
                    ]),
            ]);
    }
}
