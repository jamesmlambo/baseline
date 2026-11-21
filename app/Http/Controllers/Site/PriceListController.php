<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Services\Availability\AvailabilityService;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The guest's view of a WhatsApp price list (WA-01..03): live availability and prices for the
 * dates reception chose. Opening it never holds a room; inventory is re-checked when booking.
 */
class PriceListController extends Controller
{
    public function show(string $token, AvailabilityService $availability): View|Response
    {
        $priceList = PriceList::query()->where('token', $token)->firstOrFail();

        if (! $priceList->isUsable()) {
            return response()->view('site.price-list-expired', ['priceList' => $priceList], 410);
        }

        $priceList->forceFill([
            'first_opened_at' => $priceList->first_opened_at ?? now(),
            'open_count' => $priceList->open_count + 1,
        ])->saveQuietly();

        $results = collect($availability->search($priceList->stay(), $priceList->adults, $priceList->children))
            ->filter->isAvailable()
            ->values();

        return view('site.price-list', ['priceList' => $priceList, 'results' => $results]);
    }
}
