<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets;

use App\Enums\TaskStatus;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Assets\Pages\ManageAssets;
use App\Models\Asset;
use App\Models\Room;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Equipment register with warranties and repair history (HK-08).
 */
class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Equipment';

    protected static ?string $modelLabel = 'equipment item';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('category')->maxLength(64)->placeholder('e.g. Geyser, Pool pump')
                ->datalist(fn (): array => Asset::query()->whereNotNull('category')->distinct()->pluck('category')->all()),
            Select::make('room_id')->label('Room')->options(fn (): array => Room::query()->orderBy('sort_order')->pluck('name', 'id')->all())->searchable(),
            TextInput::make('location')->label('Or place')->maxLength(255),
            TextInput::make('serial_number')->maxLength(255),
            TextInput::make('supplier')->maxLength(255),
            DatePicker::make('purchased_on'),
            DatePicker::make('warranty_until'),
            MoneyInput::make('purchase_cost_cents')->label('Purchase cost'),
            Toggle::make('is_active')->label('In use')->default(true),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('room')->withCount(['tasks as open_tasks_count' => fn ($tasks) => $tasks->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Asset $record): ?string => $record->category),
                TextColumn::make('where')->label('Where')->state(fn (Asset $record): string => $record->room?->name ?? $record->location ?? '—'),
                TextColumn::make('warranty_until')->label('Warranty')->date('j M Y')->placeholder('—')
                    ->color(fn (Asset $record): ?string => $record->warranty_until === null ? null : ($record->isUnderWarranty() ? 'success' : 'gray')),
                TextColumn::make('open_tasks_count')->label('Open jobs'),
                IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (Asset $record): array => [
                        TextEntry::make('history')->hiddenLabel()->listWithLineBreaks()->placeholder('No jobs yet')->state(
                            $record->tasks()->with('completer')->take(30)->get()->map(fn (Task $task): string => $task->created_at->timezone('Africa/Johannesburg')->format('j M Y')
                                ." — {$task->title} ({$task->status->label()})"
                                .($task->cost_cents ? ', '.$task->cost_cents->format() : '')
                                .($task->supplier ? ", {$task->supplier}" : ''))->all(),
                        ),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssets::route('/'),
        ];
    }
}
