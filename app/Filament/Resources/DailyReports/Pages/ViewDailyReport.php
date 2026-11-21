<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyReports\Pages;

use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\DailyReports\DailyReportResource;
use App\Models\DailyReport;
use App\Services\Reports\DailyReports;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One stored daily report (REP-03/04): the 17:00 snapshot, its deliveries, and the day-close totals.
 *
 * @property DailyReport $record
 */
class ViewDailyReport extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DailyReportResource::class;

    protected string $view = 'filament.reports.view-daily-report';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('view', $this->record), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Daily report — '.$this->record->report_date->format('l j F Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeDay')
                ->label('Close the day')
                ->icon('heroicon-o-lock-closed')
                ->visible(fn (): bool => ! $this->record->isFrozen())
                ->authorize(fn (): bool => auth()->user()->can('freeze', $this->record))
                ->requiresConfirmation()
                ->modalDescription('Records today\'s reconciled money totals as they are now. The 17:00 snapshot is not changed. This cannot be undone.')
                ->action(function (): void {
                    PaymentActions::run(fn () => app(DailyReports::class)->freeze($this->record, auth()->user()), 'Day closed');
                    $this->record->refresh();
                }),
            Action::make('retryDeliveries')
                ->label('Retry failed deliveries')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->record->deliveries()->where('status', 'failed')->exists())
                ->authorize(fn (): bool => auth()->user()->can('view', $this->record))
                ->action(function (): void {
                    $this->record->deliveries()->where('status', 'failed')->get()->each(fn ($delivery) => app(DailyReports::class)->attempt($delivery));
                    $this->record->refresh();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'report' => $this->record,
            'deliveries' => $this->record->deliveries()->with('user')->orderBy('channel')->get(),
        ];
    }
}
