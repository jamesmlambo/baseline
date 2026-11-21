<?php

declare(strict_types=1);

namespace App\Filament\Staff\Pages;

use App\Services\Staff\Roster;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * EMP-03: an employee's own shifts for the next two weeks.
 */
class MyShifts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'My shifts';

    protected static ?string $title = 'My shifts';

    protected static ?string $slug = 'my-shifts';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.staff.pages.my-shifts';

    public static function canAccess(): bool
    {
        return auth()->user()?->employee()->exists() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $employee = auth()->user()->employee()->firstOrFail();

        return ['days' => app(Roster::class)->daysFor($employee, CarbonImmutable::today('Africa/Johannesburg'), 14)];
    }
}
