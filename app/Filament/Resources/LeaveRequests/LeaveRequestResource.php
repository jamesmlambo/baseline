<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveRequests;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\LeaveRequest;
use App\Services\Staff\LeaveManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Leave requests (EMP-02). Approving makes the employee off duty on those days.
 */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Leave';

    protected static ?int $navigationSort = 35;

    public static function getNavigationBadge(): ?string
    {
        $pending = LeaveRequest::query()->where('status', LeaveStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'decider']))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('type')->formatStateUsing(fn (LeaveType $state): string => $state->label()),
                TextColumn::make('starts_on')->label('From')->date('D j M Y')->sortable(),
                TextColumn::make('ends_on')->label('To')->date('D j M Y'),
                TextColumn::make('days')->label('Days')->state(fn (LeaveRequest $record): int => $record->days()),
                TextColumn::make('reason')->wrap()->limit(80)->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LeaveStatus $state): string => $state->label())
                    ->color(fn (LeaveStatus $state): string => $state->color())
                    ->description(fn (LeaveRequest $record): ?string => $record->decider?->name),
            ])
            ->filters([
                SelectFilter::make('status')->options(LeaveStatus::options())->default(LeaveStatus::Pending->value),
                SelectFilter::make('employee_id')->label('Employee')->relationship('employee', 'full_name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveStatus::Pending)
                    ->authorize(fn (LeaveRequest $record): bool => auth()->user()->can('update', $record))
                    ->schema([Textarea::make('note')->rows(2)])
                    ->action(fn (LeaveRequest $record, array $data) => PaymentActions::run(fn () => app(LeaveManager::class)->decide($record, true, $data['note'] ?? null, auth()->user()), 'Leave approved')),
                Action::make('decline')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveStatus::Pending)
                    ->authorize(fn (LeaveRequest $record): bool => auth()->user()->can('update', $record))
                    ->schema([Textarea::make('note')->label('Why')->required()->rows(2)])
                    ->action(fn (LeaveRequest $record, array $data) => PaymentActions::run(fn () => app(LeaveManager::class)->decide($record, false, $data['note'], auth()->user()), 'Leave declined')),
                Action::make('cancel')
                    ->label('Cancel leave')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (LeaveRequest $record): bool => in_array($record->status, [LeaveStatus::Pending, LeaveStatus::Approved], true))
                    ->authorize(fn (LeaveRequest $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(fn (LeaveRequest $record) => PaymentActions::run(fn () => app(LeaveManager::class)->cancel($record, auth()->user()), 'Leave cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLeaveRequests::route('/')];
    }
}
