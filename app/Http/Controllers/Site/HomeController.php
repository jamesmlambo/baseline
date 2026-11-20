<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AccommodationType;
use App\Models\Amenity;
use App\Models\Post;
use App\Models\Setting;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(RateCalculator $rates): View
    {
        $types = AccommodationType::query()
            ->active()
            ->with('media')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        // "From" price: tonight for one night at base occupancy, from the rate engine (WEB-06).
        $tonight = new StayDates(today(), today()->addDay());
        $fromPrices = $types->mapWithKeys(fn (AccommodationType $type): array => [
            $type->id => $rates->quote($type, $tonight, min($type->base_occupancy, $type->max_occupancy))->total(),
        ]);

        return view('site.home', [
            'types' => $types,
            'fromPrices' => $fromPrices,
            'posts' => Post::query()->visible()->with('media')->latest('published_at')->take(3)->get(),
            'facilities' => Amenity::query()->published()->where('category', 'property')->take(6)->get(),
            'tagline' => Setting::get('website.tagline'),
            'intro' => Setting::get('website.intro'),
        ]);
    }
}
