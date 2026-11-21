<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Services\Reception\ReceptionOverview;
use Illuminate\Database\Eloquent\Builder;

class InHouseGuests extends FrontDeskBookingsTable
{
    protected static ?string $heading = 'In house';

    protected static ?int $sort = -7;

    /**
     * @return Builder<Booking>
     */
    protected function bookings(ReceptionOverview $overview): Builder
    {
        return $overview->inHouse()->orderBy('departure_date');
    }

    protected function emptyMessage(): string
    {
        return 'Nobody checked in';
    }
}
