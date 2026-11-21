<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePunches;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Filament\Resources\AttendancePunches\Pages\ListAttendancePunches;
use App\Models\AttendancePunch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Raw clock events, read-only (ATT-02). Unmatched IDs show which terminal IDs still need an employee.
 */
class AttendancePunchResource extends Resource
{
    protected static ?string $model = AttendancePunch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Clock events';

    protected static ?int $navigationSort = 45;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'enteredBy']))
            ->defaultSort('punched_at', 'desc')
            ->columns([
                TextColumn::make('punched_at')->label('Time')->dateTime('D j M H:i:s', 'Africa/Johannesburg')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->placeholder('Not matched')
                    ->description(fn (AttendancePunch $record): ?string => $record->device_person_id),
                TextColumn::make('direction')->badge()->formatStateUsing(fn (PunchDirection $state): string => $state->label())->color(fn (PunchDirection $state): string => $state->color()),
                TextColumn::make('source')->formatStateUsing(fn (PunchSource $state): string => $state->label())
                    ->description(fn (AttendancePunch $record): ?string => $record->source === PunchSource::Manual ? "{$record->enteredBy?->name}: {$record->reason}" : $record->device_serial),
            ])
            ->filters([
                TernaryFilter::make('matched')->label('Matched to an employee')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('employee_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('employee_id'),
                    ),
                SelectFilter::make('source')->options(PunchSource::options()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttendancePunches::route('/')];
    }
}
