<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AccommodationType;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(RateCalculator $rates): View
    {
        $types = AccommodationType::query()->active()->with('media')->orderBy('sort_order')->get();
        $tonight = new StayDates(today(), today()->addDay());

        return view('site.rooms.index', [
            'types' => $types,
            'fromPrices' => $types->mapWithKeys(fn (AccommodationType $type): array => [
                $type->id => $rates->quote($type, $tonight, $type->base_occupancy)->total(),
            ]),
        ]);
    }

    public function show(string $slug, RateCalculator $rates): View
    {
        $type = AccommodationType::query()
            ->active()
            ->where('slug', $slug)
            ->with(['media', 'amenities' => fn ($query) => $query->published()])
            ->firstOrFail();

        return view('site.rooms.show', [
            'type' => $type,
            'fromPrice' => $rates->quote($type, new StayDates(today(), today()->addDay()), $type->base_occupancy)->total(),
        ]);
    }
}
