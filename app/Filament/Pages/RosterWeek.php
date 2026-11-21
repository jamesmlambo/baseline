<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Models\Employee;
use App\Services\Staff\Roster;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Who works when this week (EMP-02), with leave, holidays, changes and cover applied.
 */
class RosterWeek extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Roster (week view)';

    protected static ?string $title = 'Roster';

    protected static ?string $slug = 'roster-week';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.roster-week';

    #[Url]
    public ?string $week = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value]) ?? false;
    }

    public function previousWeek(): void
    {
        $this->week = $this->monday()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->monday()->addWeek()->toDateString();
    }

    public function monday(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->week ?? 'today', 'Africa/Johannesburg')->startOfWeek();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monday = $this->monday();
        $roster = app(Roster::class);

        return [
            'days' => collect(range(0, 6))->map(fn (int $offset): CarbonImmutable => $monday->addDays($offset)),
            'rows' => Employee::query()->active()->with('department')->orderBy('full_name')->get()
                ->map(fn (Employee $employee): array => ['employee' => $employee, 'days' => $roster->daysFor($employee, $monday, 7)]),
        ];
    }
}
