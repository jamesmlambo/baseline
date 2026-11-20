<?php

declare(strict_types=1);

namespace App\Filament\Resources\Extras;

use App\Enums\ExtraPricingUnit;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Extras\Pages\CreateExtra;
use App\Filament\Resources\Extras\Pages\EditExtra;
use App\Filament\Resources\Extras\Pages\ListExtras;
use App\Models\Extra;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ExtraResource extends Resource
{
    protected static ?string $model = Extra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Extras';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('pricing_unit')->label('Charged')->options(ExtraPricingUnit::options())->required(),
            MoneyInput::make('price_cents')->label('Price')->required(),
            TextInput::make('sort_order')->integer()->default(0),
            Textarea::make('description')->rows(2)->columnSpanFull(),
            Toggle::make('is_active')->label('Available')->default(true),
            Toggle::make('is_bookable_online')->label('Offered on the website')->default(true),
            Toggle::make('is_demo')->label('Demo data'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('price_cents')->label('Price')->formatStateUsing(fn (Money $state): string => $state->format()),
                TextColumn::make('pricing_unit')->label('Charged')->formatStateUsing(fn (ExtraPricingUnit $state): string => $state->label()),
                IconColumn::make('is_active')->label('Available')->boolean(),
                IconColumn::make('is_bookable_online')->label('Online')->boolean(),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtras::route('/'),
            'create' => CreateExtra::route('/create'),
            'edit' => EditExtra::route('/{record}/edit'),
        ];
    }
}
