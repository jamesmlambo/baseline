<?php

declare(strict_types=1);

namespace App\Filament\Resources\MenuItems;

use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuItem;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Room-service menu shown in the guest portal (GST-05). Items are switched off rather than deleted,
 * so past orders keep their history.
 */
class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Guest menu';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('category')->required()->maxLength(64)->placeholder('e.g. Breakfast')
                ->datalist(fn (): array => MenuItem::query()->distinct()->pluck('category')->all()),
            Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull(),
            TextInput::make('allergens')->maxLength(255)->placeholder('e.g. Gluten, dairy'),
            MoneyInput::make('price_cents')->label('Price')->required(),
            TextInput::make('sort_order')->integer()->default(0),
            Toggle::make('is_available')->label('Available to order')->default(true),
            Toggle::make('is_demo')->label('Demo item'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->defaultGroup('category')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (MenuItem $record): ?string => $record->allergens),
                TextColumn::make('price_cents')->label('Price')->formatStateUsing(fn (Money $state): string => $state->format()),
                ToggleColumn::make('is_available')->label('Available')
                    ->disabled(fn (MenuItem $record): bool => ! auth()->user()->can('update', $record)),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
        ];
    }
}
