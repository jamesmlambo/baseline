<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDays;

use App\Enums\AttendanceStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Models\AttendanceDay;
use App\Services\Staff\Attendance\AttendanceCorrections;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Derived attendance (ATT-03) with audited corrections (ATT-04), CSV import, manual punches and
 * the monthly export (ATT-05).
 */
class AttendanceDayResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        $time = fn (?CarbonImmutable $value): ?string => $value?->setTimezone('Africa/Johannesburg')->format('H:i');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'shift', 'corrector']))
            ->defaultSort('work_date', 'desc')
            ->columns([
                TextColumn::make('work_date')->label('Date')->date('D j M')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('shift.name')->label('Shift')->placeholder('—')
                    ->description(fn (AttendanceDay $record): ?string => $record->scheduled_start ? $time($record->scheduled_start).'–'.$time($record->scheduled_end) : null),
                TextColumn::make('first_in')->label('In')->formatStateUsing(fn (AttendanceDay $record): string => $time($record->first_in) ?? '—'),
                TextColumn::make('last_out')->label('Out')->formatStateUsing(fn (AttendanceDay $record): string => $time($record->last_out) ?? '—'),
                TextColumn::make('minutes_worked')->label('Hours')->formatStateUsing(fn (AttendanceDay $record): string => $record->hoursLabel()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (AttendanceStatus $state): string => $state->label())
                    ->color(fn (AttendanceStatus $state): string => $state->color())
                    ->description(fn (AttendanceDay $record): ?string => $record->late_minutes > 0 ? "{$record->late_minutes} min late" : null),
                IconColumn::make('is_corrected')->label('Corrected')->boolean()->trueColor('warning')
                    ->tooltip(fn (AttendanceDay $record): ?string => $record->is_corrected ? "{$record->corrector?->name}: {$record->correction_reason}" : null),
            ])
            ->filters([
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->default(today()->subDays(13)),
                        DatePicker::make('until')->default(today()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $inner, string $from) => $inner->whereDate('work_date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $inner, string $until) => $inner->whereDate('work_date', '<=', $until))),
                SelectFilter::make('status')->multiple()->options(AttendanceStatus::options()),
                SelectFilter::make('employee_id')->label('Employee')->relationship('employee', 'full_name'),
            ])
            ->recordActions([
                Action::make('correct')
                    ->label('Correct')
                    ->icon('heroicon-o-pencil-square')
                    ->authorize(fn (AttendanceDay $record): bool => auth()->user()->can('update', $record))
                    ->fillForm(fn (AttendanceDay $record): array => [
                        'status' => $record->status->value,
                        'first_in' => $record->first_in?->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'),
                        'last_out' => $record->last_out?->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'),
                    ])
                    ->schema([
                        Select::make('status')->options(AttendanceStatus::options())->required(),
                        DateTimePicker::make('first_in')->label('Clock in')->seconds(false),
                        DateTimePicker::make('last_out')->label('Clock out')->seconds(false)->after('first_in'),
                        Textarea::make('reason')->required()->rows(2)->helperText('Recorded in the audit log with your name.'),
                    ])
                    ->action(fn (AttendanceDay $record, array $data) => PaymentActions::run(fn () => app(AttendanceCorrections::class)->correct(
                        $record,
                        AttendanceStatus::from($data['status']),
                        filled($data['first_in'] ?? null) ? CarbonImmutable::parse($data['first_in'], 'Africa/Johannesburg') : null,
                        filled($data['last_out'] ?? null) ? CarbonImmutable::parse($data['last_out'], 'Africa/Johannesburg') : null,
                        $data['reason'],
                        auth()->user(),
                    ), 'Attendance corrected')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttendanceDays::route('/')];
    }
}
