<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyLeave;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Actions\PaymentActions;
use App\Filament\Staff\Resources\MyLeave\Pages\ListMyLeave;
use App\Models\LeaveRequest;
use App\Services\Staff\LeaveManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * EMP-03: an employee's own leave requests.
 */
class MyLeaveResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $slug = 'my-leave';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $navigationLabel = 'My leave';

    protected static ?string $modelLabel = 'leave request';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->employee()->exists() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('employee', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('type')->formatStateUsing(fn (LeaveType $state): string => $state->label()),
                TextColumn::make('starts_on')->label('From')->date('D j M Y'),
                TextColumn::make('ends_on')->label('To')->date('D j M Y'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LeaveStatus $state): string => $state->label())
                    ->color(fn (LeaveStatus $state): string => $state->color())
                    ->description(fn (LeaveRequest $record): ?string => $record->decision_note),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveStatus::Pending || ($record->status === LeaveStatus::Approved && $record->starts_on->isFuture()))
                    ->requiresConfirmation()
                    ->action(fn (LeaveRequest $record) => PaymentActions::run(fn () => app(LeaveManager::class)->cancel($record, auth()->user()), 'Leave cancelled')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMyLeave::route('/')];
    }
}
