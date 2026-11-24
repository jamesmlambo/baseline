<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Actions\HousekeepingActions;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newTask')
                ->label('New task')
                ->icon('heroicon-o-plus')
                ->authorize(fn (): bool => auth()->user()->can('create', Task::class))
                ->schema(fn (Schema $schema): Schema => TaskResource::form($schema)->columns(2))
                ->action(fn (array $data) => PaymentActions::run(
                    fn () => TaskResource::createFromForm($data, auth()->user()),
                    ($data['schedule'] ?? TaskResource::ONE_DAY) === TaskResource::ONE_DAY ? 'Task created' : 'Repeating task created',
                )),
            HousekeepingActions::reportFault(),
        ];
    }
}
