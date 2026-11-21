<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\Stock;

use App\Enums\StockMovementReason;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Staff\Resources\Stock\Pages\ListStaffStock;
use App\Models\StockItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Workers record stock they receive, use or find damaged (HK-09). Corrections are for managers in /admin.
 */
class StaffStockResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static ?string $slug = 'stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Stock';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_active', true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withSum('movements', 'quantity'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (StockItem $record): ?string => $record->category),
                TextColumn::make('movements_sum_quantity')->label('On hand')->default(0)
                    ->formatStateUsing(fn (StockItem $record): string => $record->onHand().' '.$record->unit)
                    ->color(fn (StockItem $record): ?string => $record->isLow() ? 'danger' : null),
            ])
            ->recordActions([
                HousekeepingActions::stockMovement(StockMovementReason::Used),
                HousekeepingActions::stockMovement(StockMovementReason::Received),
                HousekeepingActions::stockMovement(StockMovementReason::Damaged),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffStock::route('/'),
        ];
    }
}
