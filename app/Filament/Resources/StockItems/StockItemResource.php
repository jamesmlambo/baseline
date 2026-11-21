<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockItems;

use App\Enums\StockMovementReason;
use App\Filament\Actions\HousekeepingActions;
use App\Filament\Resources\StockItems\Pages\ManageStockItems;
use App\Models\StockItem;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Stock ledger (HK-09): quantities change only by recording what came in or went out.
 */
class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $low = StockItem::query()->low()->count();

        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('category')->maxLength(64)->placeholder('e.g. Toiletries')
                ->datalist(fn (): array => StockItem::query()->whereNotNull('category')->distinct()->pluck('category')->all()),
            TextInput::make('unit')->required()->default('each')->maxLength(32)->placeholder('each, roll, kg, litre…'),
            TextInput::make('reorder_level')->label('Warn when at or below')->integer()->minValue(0),
            Toggle::make('is_active')->label('In use')->default(true),
            Toggle::make('is_demo')->label('Demo item'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withSum('movements', 'quantity'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (StockItem $record): ?string => $record->category),
                TextColumn::make('movements_sum_quantity')->label('On hand')
                    ->formatStateUsing(fn (StockItem $record): string => $record->onHand().' '.$record->unit)
                    ->default(0)
                    ->color(fn (StockItem $record): ?string => $record->isLow() ? 'danger' : null)
                    ->weight(fn (StockItem $record): ?string => $record->isLow() ? 'bold' : null),
                TextColumn::make('reorder_level')->label('Reorder at')->placeholder('—'),
                IconColumn::make('is_demo')->label('Demo')->boolean()->trueColor('warning')->falseColor('success'),
            ])
            ->filters([
                Filter::make('low')->label('Low stock only')->query(fn (Builder $query): Builder => $query->low()),
            ])
            ->recordActions([
                HousekeepingActions::stockMovement(StockMovementReason::Received),
                HousekeepingActions::stockMovement(StockMovementReason::Used),
                ActionGroup::make([
                    HousekeepingActions::stockMovement(StockMovementReason::Damaged),
                    HousekeepingActions::stockMovement(StockMovementReason::Adjusted),
                    Action::make('ledger')
                        ->label('History')
                        ->icon('heroicon-o-clock')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->schema(fn (StockItem $record): array => [
                            TextEntry::make('ledger')->hiddenLabel()->listWithLineBreaks()->placeholder('Nothing recorded yet')->state(
                                $record->movements()->with('user')->latest('id')->take(40)->get()->map(fn (StockMovement $movement): string => $movement->created_at->timezone('Africa/Johannesburg')->format('j M H:i')
                                    .' · '.($movement->quantity > 0 ? '+' : '').$movement->quantity.' '.$record->unit
                                    .' · '.$movement->reason->label()
                                    .' · '.($movement->user?->name ?? 'system')
                                    .($movement->note ? " — {$movement->note}" : ''))->all(),
                            ),
                        ]),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockItems::route('/'),
        ];
    }
}
