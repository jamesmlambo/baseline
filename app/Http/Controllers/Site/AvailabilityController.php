<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\AvailabilitySearchRequest;
use App\Services\Availability\AvailabilityService;
use Illuminate\View\View;

/**
 * Public availability search (page 14). Shows live counts and prices from the
 * AvailabilityService; it never holds inventory (WA-03, INV-06).
 */
class AvailabilityController extends Controller
{
    public function __invoke(AvailabilitySearchRequest $request, AvailabilityService $availability): View
    {
        $stay = $request->stay();

        return view('site.availability', [
            'stay' => $stay,
            'adults' => $request->adults(),
            'children' => $request->children(),
            'results' => $stay !== null ? $availability->search($stay, $request->adults(), $request->children()) : null,
        ]);
    }
}
