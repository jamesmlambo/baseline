<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Services\Reception\RoomCalendar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Room-by-date calendar (REC-02): every room for 14 nights, coloured by status.
 */
class RoomCalendarPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Room calendar';

    protected static ?string $title = 'Room calendar';

    protected static ?string $slug = 'room-calendar';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.room-calendar';

    #[Url]
    public string $start = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }

    public function startDate(): CarbonImmutable
    {
        try {
            return $this->start !== '' ? CarbonImmutable::parse($this->start) : today()->toImmutable()->subDay();
        } catch (Throwable) {
            return today()->toImmutable()->subDay();
        }
    }

    public function move(int $days): void
    {
        $this->start = $this->startDate()->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->start = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return app(RoomCalendar::class)->build($this->startDate());
    }
}
