<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Services\Reception\ReceptionOverview;
use Illuminate\Database\Eloquent\Builder;

class DeparturesToday extends FrontDeskBookingsTable
{
    protected static ?string $heading = 'Leaving today';

    protected static ?int $sort = -8;

    /**
     * @return Builder<Booking>
     */
    protected function bookings(ReceptionOverview $overview): Builder
    {
        return $overview->departures()->orderBy('lead_guest_name');
    }

    protected function emptyMessage(): string
    {
        return 'No departures today';
    }
}
