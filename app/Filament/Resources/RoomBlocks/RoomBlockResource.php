<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoomBlocks;

use App\Filament\Resources\RoomBlocks\Pages\CreateRoomBlock;
use App\Filament\Resources\RoomBlocks\Pages\ListRoomBlocks;
use App\Filament\Resources\RoomBlocks\Schemas\RoomBlockForm;
use App\Filament\Resources\RoomBlocks\Tables\RoomBlocksTable;
use App\Models\RoomBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RoomBlockResource extends Resource
{
    protected static ?string $model = RoomBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Room blocks';

    protected static ?string $modelLabel = 'room block';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return RoomBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomBlocks::route('/'),
            'create' => CreateRoomBlock::route('/create'),
        ];
    }
}
