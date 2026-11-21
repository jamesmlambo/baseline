<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ServiceBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Services\Leisure\ServiceAvailability;
use App\Services\Leisure\ServiceBookings;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * GST-07 / LSR-05: in-house guests book spa, day-pass, braai and VIP slots and charge them to the room.
 */
class PortalLeisureController extends Controller
{
    public function __construct(
        private readonly ServiceAvailability $availability,
        private readonly ServiceBookings $bookings,
    ) {}

    public function index(Request $request): View
    {
        $stay = $this->stay($request);
        $services = Service::query()->active()->where('bookable_by_guests', true)->get();
        $selected = $services->firstWhere('id', (int) $request->query('service'));
        $today = CarbonImmutable::today('Africa/Johannesburg');

        try {
            $date = CarbonImmutable::parse((string) $request->query('date', $today->toDateString()), 'Africa/Johannesburg')->startOfDay();
        } catch (Throwable) {
            $date = $today;
        }

        $date = $date->lessThan($today) ? $today : $date;
        $party = max(1, min(20, (int) $request->query('people', 1)));

        return view('portal.book', [
            'booking' => $stay,
            'services' => $services,
            'selected' => $selected,
            'date' => $date,
            'people' => $party,
            'slots' => $selected !== null ? $this->availability->slots($selected, $date, $party) : [],
            'needsIndemnity' => $selected !== null && $selected->requires_indemnity && Features::enabled(Features::INDEMNITY),
            'myBookings' => ServiceBooking::query()->with('service')->where('booking_id', $stay->id)->latest('starts_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $stay = $this->stay($request);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->where('bookable_by_guests', true)],
            'start' => ['required', 'date'],
            'people' => ['required', 'integer', 'min:1', 'max:20'],
            'indemnity' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service = Service::query()->findOrFail($validated['service_id']);

        try {
            $this->bookings->book(
                $service,
                CarbonImmutable::parse($validated['start']),
                (int) $validated['people'],
                ['name' => $stay->lead_guest_name, 'notes' => $validated['notes'] ?? null],
                $stay,
                $service->charge_to_room_allowed,
                ServiceBooking::SOURCE_PORTAL,
                indemnityAccepted: $request->boolean('indemnity'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['start' => $exception->getMessage()]);
        }

        return redirect()->route('portal.book')->with('status', "Booked: {$service->name}. See you then!");
    }

    public function cancel(Request $request, int $serviceBooking): RedirectResponse
    {
        $stay = $this->stay($request);
        $booking = ServiceBooking::query()->where('booking_id', $stay->id)->where('status', ServiceBookingStatus::Confirmed)->findOrFail($serviceBooking);

        try {
            $this->bookings->cancel($booking, 'Cancelled by the guest in the portal');
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('portal.book')->withErrors(['cancel' => $exception->getMessage()]);
        }

        return redirect()->route('portal.book')->with('status', 'Booking cancelled.');
    }

    private function stay(Request $request): Booking
    {
        return $request->attributes->get('portal_booking');
    }
}
