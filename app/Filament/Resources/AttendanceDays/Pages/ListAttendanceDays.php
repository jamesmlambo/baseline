<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDays\Pages;

use App\Enums\PunchDirection;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\AttendanceDays\AttendanceDayResource;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Services\Staff\Attendance\AttendanceCalculator;
use App\Services\Staff\Attendance\AttendanceExport;
use App\Services\Staff\Attendance\CsvAttendanceSource;
use App\Services\Staff\Attendance\PunchRecorder;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCsv')
                ->label('Import clock CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->authorize(fn (): bool => auth()->user()->can('create', AttendanceDay::class))
                ->modalDescription('Needs a header row with a person ID column and a time column (direction and device are optional). Importing the same file twice adds nothing new.')
                ->schema([
                    FileUpload::make('file')->label('CSV file')->required()->disk('local')->directory('attendance-imports')->visibility('private')
                        ->rules(['extensions:csv,txt'])->maxSize(5120),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $batch = app(PunchRecorder::class)->import(new CsvAttendanceSource($path), auth()->user(), basename($data['file']));
                    } catch (Throwable $exception) {
                        Notification::make()->title('Not imported')->body($exception->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    Notification::make()
                        ->title('Import finished')
                        ->body("{$batch->rows_added} new, {$batch->rows_duplicate} already imported, {$batch->rows_failed} unreadable.".($batch->rows_failed > 0 ? ' First problem: row '.($batch->errors[0]['row'] ?? '?').' — '.($batch->errors[0]['message'] ?? '') : ''))
                        ->color($batch->rows_failed > 0 ? 'warning' : 'success')
                        ->send();
                }),
            ActionGroup::make([
                Action::make('manualPunch')
                    ->label('Add a punch by hand')
                    ->icon('heroicon-o-hand-raised')
                    ->authorize(fn (): bool => auth()->user()->can('create', AttendanceDay::class))
                    ->schema([
                        Select::make('employee_id')->label('Employee')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable()->required(),
                        DateTimePicker::make('punched_at')->label('Time')->seconds(false)->required()->maxDate(now()),
                        Select::make('direction')->options([PunchDirection::In->value => 'Clock in', PunchDirection::Out->value => 'Clock out'])->required(),
                        Textarea::make('reason')->required()->rows(2)->placeholder('e.g. Terminal was offline'),
                    ])
                    ->action(fn (array $data) => PaymentActions::run(fn () => app(PunchRecorder::class)->manual(
                        Employee::query()->findOrFail($data['employee_id']),
                        CarbonImmutable::parse($data['punched_at'], 'Africa/Johannesburg'),
                        PunchDirection::from($data['direction']),
                        $data['reason'],
                        auth()->user(),
                    ), 'Punch added')),
                Action::make('recalculate')
                    ->label('Recalculate a day')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn (): bool => auth()->user()->can('create', AttendanceDay::class))
                    ->schema([DatePicker::make('date')->required()->default(today()->subDay())->maxDate(today())])
                    ->modalDescription('Corrected days are left as they are.')
                    ->action(function (array $data): void {
                        $count = app(AttendanceCalculator::class)->deriveDate(CarbonImmutable::parse($data['date'], 'Africa/Johannesburg'));
                        Notification::make()->title("{$count} day(s) updated")->success()->send();
                    }),
                Action::make('export')
                    ->label('Export month (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->authorize(fn (): bool => auth()->user()->can('create', AttendanceDay::class))
                    ->schema([DatePicker::make('month')->label('Any day in the month')->required()->default(today()->subMonthNoOverflow()->startOfMonth())])
                    ->action(function (array $data): StreamedResponse {
                        $month = CarbonImmutable::parse($data['month']);
                        $csv = app(AttendanceExport::class)->month($month->year, $month->month, auth()->user());

                        return response()->streamDownload(fn () => print ($csv), "attendance-{$month->format('Y-m')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ])->label('More')->button()->color('gray'),
        ];
    }
}
