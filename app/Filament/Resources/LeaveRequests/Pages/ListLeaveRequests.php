<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Enums\LeaveType;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Staff\LeaveManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordLeave')
                ->label('Record leave')
                ->icon('heroicon-o-plus')
                ->authorize(fn (): bool => auth()->user()->can('create', LeaveRequest::class))
                ->schema([
                    Select::make('employee_id')->label('Employee')->options(fn (): array => Employee::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())->searchable()->required(),
                    Select::make('type')->options(LeaveType::options())->required(),
                    DatePicker::make('starts_on')->label('First day')->required(),
                    DatePicker::make('ends_on')->label('Last day')->required()->afterOrEqual('starts_on'),
                    Textarea::make('reason')->rows(2),
                ])
                ->modalDescription('Saved as a request; approve it in the list.')
                ->action(fn (array $data) => PaymentActions::run(fn () => app(LeaveManager::class)->request(
                    Employee::query()->findOrFail($data['employee_id']),
                    LeaveType::from($data['type']),
                    CarbonImmutable::parse($data['starts_on'], 'Africa/Johannesburg'),
                    CarbonImmutable::parse($data['ends_on'], 'Africa/Johannesburg'),
                    $data['reason'] ?? null,
                    auth()->user(),
                ), 'Leave recorded')),
        ];
    }
}
