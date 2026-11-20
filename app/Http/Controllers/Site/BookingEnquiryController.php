<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Website\RecordEnquiry;
use App\Enums\EnquiryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\BookingEnquiryRequest;
use App\Models\AccommodationType;
use App\Services\Availability\AvailabilityService;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * "Book accommodation" (page 15). Until online booking and payment arrive in Milestone 4,
 * this sends a booking request to reception. It holds no inventory, and the guest is told so.
 */
class BookingEnquiryController extends Controller
{
    public function create(Request $request, AvailabilityService $availability, RateCalculator $rates): View|RedirectResponse
    {
        $type = AccommodationType::query()->active()->find($request->integer('type'));
        $stay = $this->stayFrom($request);
        $adults = max(1, $request->integer('adults', 2));
        $children = max(0, $request->integer('children'));

        if ($type === null || $stay === null || $stay->arrival->isPast() && ! $stay->arrival->isToday()) {
            return redirect()->route('availability');
        }

        try {
            $quote = $rates->quote($type, $stay, $adults, $children);
        } catch (Throwable) {
            return redirect()->route('availability', [
                'arrival' => $stay->arrivalString(),
                'departure' => $stay->departureString(),
                'adults' => $adults,
                'children' => $children,
            ]);
        }

        return view('site.book', [
            'type' => $type,
            'stay' => $stay,
            'adults' => $adults,
            'children' => $children,
            'quote' => $quote,
            'isAvailable' => $availability->availableRoomsQuery($type, $stay)->exists(),
        ]);
    }

    public function store(BookingEnquiryRequest $request, RecordEnquiry $record, RateCalculator $rates): RedirectResponse
    {
        $type = AccommodationType::query()->findOrFail($request->integer('accommodation_type_id'));
        $stay = new StayDates($request->string('arrival')->value(), $request->string('departure')->value());

        try {
            $total = $rates->quote($type, $stay, $request->integer('adults'), $request->integer('children'))->total();
        } catch (Throwable) {
            return back()->withErrors(['adults' => "{$type->name} sleeps at most {$type->max_occupancy}."])->withInput();
        }

        $record->handle(EnquiryType::BookingRequest, [
            ...$request->safe()->only(['name', 'email', 'phone', 'message', 'adults', 'children', 'marketing_consent']),
            'accommodation_type_id' => $type->id,
            'arrival_date' => $stay->arrivalString(),
            'departure_date' => $stay->departureString(),
            'quoted_total_cents' => $total,
        ], $request->ip());

        return redirect()->route('home')->with(
            'status',
            'Thank you — your booking request has been sent. Your room is not reserved yet: reception will contact you to confirm availability and payment.',
        );
    }

    private function stayFrom(Request $request): ?StayDates
    {
        try {
            return new StayDates((string) $request->query('arrival'), (string) $request->query('departure'));
        } catch (Throwable) {
            return null;
        }
    }
}
