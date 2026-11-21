<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyReports;

use App\Filament\Resources\DailyReports\Pages\ListDailyReports;
use App\Filament\Resources\DailyReports\Pages\ViewDailyReport;
use App\Models\DailyReport;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Archive of daily owner reports (REP-04).
 */
class DailyReportResource extends Resource
{
    protected static ?string $model = DailyReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Daily reports';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'deliveries as sent_count' => fn (Builder $deliveries) => $deliveries->where('status', 'sent'),
                'deliveries as failed_count' => fn (Builder $deliveries) => $deliveries->where('status', 'failed'),
            ]))
            ->defaultSort('report_date', 'desc')
            ->columns([
                TextColumn::make('report_date')->label('Day')->date('D j M Y')->sortable(),
                TextColumn::make('occupancy')->label('Occupancy')
                    ->state(fn (DailyReport $record): string => isset($record->data['rooms']['occupancy_percent']) ? $record->data['rooms']['occupancy_percent'].' %' : '—'),
                TextColumn::make('payments')->label('Payments received')
                    ->state(fn (DailyReport $record): string => Money::cents((int) ($record->data['money']['payments_received_cents'] ?? 0))->format()),
                TextColumn::make('generated_at')->label('Generated')->dateTime('H:i', 'Africa/Johannesburg'),
                TextColumn::make('sent_count')->label('Sent'),
                TextColumn::make('failed_count')->label('Failed')->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                IconColumn::make('frozen_at')->label('Day closed')->boolean()->state(fn (DailyReport $record): bool => $record->isFrozen()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyReports::route('/'),
            'view' => ViewDailyReport::route('/{record}'),
        ];
    }
}
