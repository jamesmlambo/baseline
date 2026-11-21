<?php

declare(strict_types=1);

namespace App\Filament\Resources\Channels\Pages;

use App\Filament\Resources\Channels\ChannelResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListChannels extends ListRecords
{
    protected static string $resource = ChannelResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'No channel manager is connected yet, so nothing syncs automatically. Channel bookings must be entered at reception, or blocked with the limited iCal fallback (dates only — it is not a channel manager).';
    }
}
