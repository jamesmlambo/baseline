<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyLeave\Pages;

use App\Enums\LeaveType;
use App\Filament\Actions\PaymentActions;
use App\Filament\Staff\Resources\MyLeave\MyLeaveResource;
use App\Services\Staff\LeaveManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;

class ListMyLeave extends ListRecords
{
    protected static string $resource = MyLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestLeave')
                ->label('Ask for leave')
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('type')->options(LeaveType::options())->required(),
                    DatePicker::make('starts_on')->label('First day')->required(),
                    DatePicker::make('ends_on')->label('Last day')->required()->afterOrEqual('starts_on'),
                    Textarea::make('reason')->rows(2),
                ])
                ->action(fn (array $data) => PaymentActions::run(fn () => app(LeaveManager::class)->request(
                    auth()->user()->employee()->firstOrFail(),
                    LeaveType::from($data['type']),
                    CarbonImmutable::parse($data['starts_on'], 'Africa/Johannesburg'),
                    CarbonImmutable::parse($data['ends_on'], 'Africa/Johannesburg'),
                    $data['reason'] ?? null,
                    auth()->user(),
                ), 'Sent — a manager will reply')),
        ];
    }
}
