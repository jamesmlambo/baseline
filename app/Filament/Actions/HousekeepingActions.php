<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Filament\Forms\MoneyInput;
use App\Models\Asset;
use App\Models\Inspection;
use App\Models\Room;
use App\Models\StockItem;
use App\Models\Task;
use App\Models\User;
use App\Services\Housekeeping\Inspections;
use App\Services\Housekeeping\StockLedger;
use App\Services\Housekeeping\TaskManager;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Storage;

/**
 * Buttons for tasks, inspections, faults and stock, shared by the admin and staff panels.
 * Each one calls the service the tests cover, so the rules cannot be bypassed from the UI.
 */
final class HousekeepingActions
{
    public static function start(): Action
    {
        return Action::make('start')
            ->label('Start')
            ->icon('heroicon-o-play')
            ->color('info')
            ->visible(fn (Task $record): bool => $record->status->value === 'open')
            ->authorize(fn (Task $record): bool => auth()->user()->can('work', $record))
            ->action(fn (Task $record) => PaymentActions::run(fn () => app(TaskManager::class)->start($record, auth()->user()), 'Started'));
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Done')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize(fn (Task $record): bool => auth()->user()->can('work', $record))
            ->modalHeading(fn (Task $record): string => $record->title)
            ->schema(fn (Task $record): array => array_values(array_filter([
                $record->checklistItems() !== []
                    ? CheckboxList::make('ticked')
                        ->label('Checklist — tick everything you did')
                        ->options(collect($record->checklistItems())->mapWithKeys(fn (array $item): array => [$item['label'] => $item['label']])->all())
                        ->required()
                        ->bulkToggleable()
                    : null,
                Textarea::make('completion_notes')
                    ->label($record->checklistItems() === [] ? 'What did you do?' : 'Notes (optional)')
                    ->required($record->checklistItems() === [])
                    ->rows(2)
                    ->maxLength(2000),
                FileUpload::make('photos')
                    ->label('Photos (optional)')
                    ->image()
                    ->multiple()
                    ->maxFiles(3)
                    ->maxSize(6144)
                    ->disk('local')
                    ->directory('task-uploads')
                    ->visibility('private'),
                $record->type->isHousekeeping() ? null : MoneyInput::make('cost')->label('Cost of parts / supplier (optional)'),
                $record->type->isHousekeeping() ? null : TextInput::make('supplier')->maxLength(255),
            ])))
            ->action(function (Task $record, array $data): void {
                PaymentActions::run(fn () => app(TaskManager::class)->complete(
                    $record,
                    auth()->user(),
                    array_values($data['ticked'] ?? []),
                    $data['completion_notes'] ?? null,
                    array_map(fn (string $path): string => Storage::disk('local')->path($path), array_values($data['photos'] ?? [])),
                    ($data['cost'] ?? null) instanceof Money ? $data['cost'] : null,
                    $data['supplier'] ?? null,
                ), $record->type->needsInspection() ? 'Done — the room is waiting for inspection' : 'Done');
            });
    }

    public static function inspect(): Action
    {
        return Action::make('inspect')
            ->label('Inspect')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (Inspection $record): bool => $record->status->value === 'pending')
            ->authorize(fn (Inspection $record): bool => auth()->user()->can('inspect', $record))
            ->modalHeading(fn (Inspection $record): string => 'Inspect '.($record->room?->name ?? $record->task->locationLabel()))
            ->modalDescription(fn (Inspection $record): string => "Score each item from 0 to {$record->scale_max}. Anything below {$record->pass_mark} fails and goes back to the cleaner as rework.")
            ->schema(fn (Inspection $record): array => [
                ...collect(app(Inspections::class)->criteriaFor($record))->values()->map(fn (string $criterion, int $index): Fieldset => Fieldset::make($criterion)
                    ->columns(2)
                    ->schema([
                        Select::make("scores.{$index}.score")
                            ->label('Score')
                            ->options(array_combine(range(0, $record->scale_max), array_map('strval', range(0, $record->scale_max))))
                            ->required(),
                        TextInput::make("scores.{$index}.comment")->label('Comment')->maxLength(255),
                    ]))->all(),
                Textarea::make('notes')->label('Overall notes')->rows(2),
            ])
            ->action(function (Inspection $record, array $data): void {
                $criteria = app(Inspections::class)->criteriaFor($record);
                $scores = [];

                foreach ($criteria as $index => $criterion) {
                    $scores[$criterion] = $data['scores'][$index] ?? [];
                }

                PaymentActions::run(fn () => app(Inspections::class)->record($record, auth()->user(), $scores, $data['notes'] ?? null), 'Inspection recorded');
            });
    }

    public static function reportFault(): Action
    {
        return Action::make('reportFault')
            ->label('Report a fault')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('danger')
            ->authorize(fn (): bool => auth()->user()->can('reportFault', Task::class))
            ->schema([
                Textarea::make('description')->label('What is wrong?')->required()->rows(3)->maxLength(2000),
                Select::make('room_id')->label('Room')->options(fn (): array => Room::query()->active()->orderBy('sort_order')->pluck('name', 'id')->all())->searchable(),
                Select::make('asset_id')->label('Item / equipment')->options(fn (): array => Asset::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                TextInput::make('location')->label('Or another place')->placeholder('e.g. Pool pump room')->maxLength(255),
                Select::make('priority')->options(TaskPriority::options())->default(TaskPriority::Normal->value)->required(),
            ])
            ->action(fn (array $data) => PaymentActions::run(fn () => app(TaskManager::class)->reportFault(
                auth()->user(),
                $data['description'],
                isset($data['room_id']) ? Room::query()->find($data['room_id']) : null,
                isset($data['asset_id']) ? (int) $data['asset_id'] : null,
                $data['location'] ?? null,
                TaskPriority::from($data['priority']),
            ), 'Fault reported — managers have been told'));
    }

    /**
     * Receive, use or (managers) correct stock.
     */
    public static function stockMovement(StockMovementReason $reason): Action
    {
        return Action::make('stock'.ucfirst($reason->value))
            ->label(match ($reason) {
                StockMovementReason::Received => 'Received',
                StockMovementReason::Used => 'Used',
                StockMovementReason::Damaged => 'Damaged / lost',
                StockMovementReason::Adjusted => 'Correct count',
            })
            ->icon(match ($reason) {
                StockMovementReason::Received => 'heroicon-o-arrow-down-tray',
                StockMovementReason::Used => 'heroicon-o-arrow-up-tray',
                StockMovementReason::Damaged => 'heroicon-o-exclamation-triangle',
                StockMovementReason::Adjusted => 'heroicon-o-scale',
            })
            ->color($reason === StockMovementReason::Received ? 'success' : 'gray')
            ->authorize(fn (StockItem $record): bool => $reason === StockMovementReason::Adjusted
                ? auth()->user()->can('adjust', $record)
                : auth()->user()->can('recordMovement', $record))
            ->schema([
                Text::make(fn (StockItem $record): string => "On hand: {$record->onHand()} {$record->unit}"),
                TextInput::make('quantity')
                    ->label($reason === StockMovementReason::Adjusted ? 'Change (+ or −)' : 'Quantity')
                    ->integer()
                    ->required()
                    ->minValue($reason === StockMovementReason::Adjusted ? null : 1),
                TextInput::make('note')->required($reason === StockMovementReason::Adjusted)->maxLength(255),
            ])
            ->action(fn (StockItem $record, array $data) => PaymentActions::run(
                fn () => app(StockLedger::class)->record($record, $reason, (int) $data['quantity'], auth()->user(), $data['note'] ?? null),
                'Stock updated',
            ));
    }

    /**
     * People a task of this type can be given to (HK-03).
     *
     * @return array<int, string>
     */
    public static function assigneeOptions(?TaskType $type): array
    {
        $roles = $type === null || $type->isHousekeeping()
            ? [StaffRole::HousekeepingWorker, StaffRole::Manager, StaffRole::Owner]
            : [StaffRole::MaintenanceWorker, StaffRole::HousekeepingWorker, StaffRole::Manager, StaffRole::Owner];

        return User::query()
            ->role(array_map(fn (StaffRole $role): string => $role->value, $roles))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
