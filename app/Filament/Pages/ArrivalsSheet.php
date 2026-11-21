<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Services\Reception\ReceptionOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Printable offline contingency sheet (OFF-01..03). Reception devices are shared and unmanaged,
 * so instead of caching guest data in the browser this produces a paper sheet with the minimum
 * needed: no phone numbers, emails or ID numbers. It is read-only by nature: no bookings,
 * payments or room changes can be made from it (DEC-08).
 */
class ArrivalsSheet extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Offline arrivals sheet';

    protected static ?string $title = 'Offline arrivals sheet';

    protected static ?string $slug = 'arrivals-sheet';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.arrivals-sheet';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = app(ReceptionOverview::class);
        $with = ['activeBookingRooms.room', 'activeBookingRooms.accommodationType'];

        return [
            'generatedAt' => now(),
            'arrivalsToday' => $overview->arrivals()->with($with)->orderBy('lead_guest_name')->get(),
            'arrivalsTomorrow' => $overview->arrivals(today()->addDay()->toImmutable())->with($with)->orderBy('lead_guest_name')->get(),
            'inHouse' => $overview->inHouse()->with($with)->orderBy('departure_date')->get(),
        ];
    }
}
