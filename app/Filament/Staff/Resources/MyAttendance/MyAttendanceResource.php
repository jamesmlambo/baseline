<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyAttendance;

use App\Enums\AttendanceStatus;
use App\Filament\Staff\Resources\MyAttendance\Pages\ListMyAttendance;
use App\Models\AttendanceDay;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * EMP-03: an employee's own attendance, read-only. Questions go to a manager, who can correct it.
 */
class MyAttendanceResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;

    protected static ?string $slug = 'my-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $navigationLabel = 'My attendance';

    protected static ?int $navigationSort = 7;

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
        $time = fn (?CarbonImmutable $value): string => $value?->setTimezone('Africa/Johannesburg')->format('H:i') ?? '—';

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('shift'))
            ->defaultSort('work_date', 'desc')
            ->columns([
                TextColumn::make('work_date')->label('Date')->date('D j M'),
                TextColumn::make('shift.name')->label('Shift')->placeholder('—'),
                TextColumn::make('first_in')->label('In')->formatStateUsing(fn (AttendanceDay $record): string => $time($record->first_in)),
                TextColumn::make('last_out')->label('Out')->formatStateUsing(fn (AttendanceDay $record): string => $time($record->last_out)),
                TextColumn::make('minutes_worked')->label('Hours')->formatStateUsing(fn (AttendanceDay $record): string => $record->hoursLabel()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color())
                    ->description(fn (AttendanceDay $record): ?string => $record->is_corrected ? 'Corrected: '.$record->correction_reason : null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMyAttendance::route('/')];
    }
}
