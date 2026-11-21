<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Services\Reception\ReceptionOverview;
use Illuminate\Database\Eloquent\Builder;

class ArrivalsToday extends FrontDeskBookingsTable
{
    protected static ?string $heading = 'Arriving today';

    protected static ?int $sort = -9;

    /**
     * @return Builder<Booking>
     */
    protected function bookings(ReceptionOverview $overview): Builder
    {
        return $overview->arrivals()->orderBy('lead_guest_name');
    }

    protected function emptyMessage(): string
    {
        return 'No arrivals today';
    }
}
