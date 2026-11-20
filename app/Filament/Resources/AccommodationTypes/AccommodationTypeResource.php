<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccommodationTypes;

use App\Filament\Resources\AccommodationTypes\Pages\CreateAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\EditAccommodationType;
use App\Filament\Resources\AccommodationTypes\Pages\ListAccommodationTypes;
use App\Filament\Resources\AccommodationTypes\RelationManagers\RoomsRelationManager;
use App\Filament\Resources\AccommodationTypes\RelationManagers\SeasonalRatesRelationManager;
use App\Filament\Resources\AccommodationTypes\Schemas\AccommodationTypeForm;
use App\Filament\Resources\AccommodationTypes\Tables\AccommodationTypesTable;
use App\Models\AccommodationType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccommodationTypeResource extends Resource
{
    protected static ?string $model = AccommodationType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Rooms & rates';

    protected static ?string $modelLabel = 'room type';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return AccommodationTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccommodationTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RoomsRelationManager::class,
            SeasonalRatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccommodationTypes::route('/'),
            'create' => CreateAccommodationType::route('/create'),
            'edit' => EditAccommodationType::route('/{record}/edit'),
        ];
    }
}
