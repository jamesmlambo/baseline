<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicHolidays;

use App\Filament\Resources\PublicHolidays\Pages\ManagePublicHolidays;
use App\Models\PublicHoliday;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Public holidays, entered by the lodge (EMP-02).
 */
class PublicHolidayResource extends Resource
{
    protected static ?string $model = PublicHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date('D j M Y'),
                TextColumn::make('name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePublicHolidays::route('/')];
    }
}
