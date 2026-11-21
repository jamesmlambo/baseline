<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Bookings\CreateReservation;
use App\Actions\Bookings\Data\ReservationRequest;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\RoomUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ReservationFormRequest;
use App\Mail\BookingReceived;
use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Extra;
use App\Models\PriceList;
use App\Services\Availability\AvailabilityService;
use App\Services\Finance\FinanceSettings;
use App\Services\Pricing\RateCalculator;
use App\Support\StayDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * Online booking (page 15, §8): choose extras, enter details, pick a payment method.
 * Creates a held booking with an invoice, then sends the guest to pay.
 */
class BookingController extends Controller
{
    public function create(Request $request, AvailabilityService $availability, RateCalculator $rates, FinanceSettings $finance): View|RedirectResponse
    {
        $type = AccommodationType::query()->active()->find($request->integer('type'));
        $stay = $this->stayFrom($request);
        $adults = max(1, $request->integer('adults', 2));
        $children = max(0, $request->integer('children'));

        if ($type === null || $stay === null || ($stay->arrival->isPast() && ! $stay->arrival->isToday())) {
            return redirect()->route('availability');
        }

        try {
            $quote = $rates->quote($type, $stay, $adults, $children);
        } catch (InvalidArgumentException) {
            return redirect()->route('availability', ['arrival' => $stay->arrivalString(), 'departure' => $stay->departureString(), 'adults' => $adults, 'children' => $children])
                ->withErrors(['adults' => "{$type->name} sleeps at most {$type->max_occupancy}."]);
        }

        return view('site.book', [
            'type' => $type,
            'stay' => $stay,
            'adults' => $adults,
            'children' => $children,
            'quote' => $quote,
            'extras' => Extra::query()->online()->get(),
            'methods' => PaymentMethod::onlineOrder(),
            'depositPercent' => $finance->depositPercent(),
            'holdMinutes' => $finance->onlineHoldMinutes(),
            'bankDetailsAvailable' => $finance->bankDetails() !== null,
            'isAvailable' => $availability->availableRoomsQuery($type, $stay)->exists(),
            'priceListToken' => self::usablePriceList($request->query('price_list'))?->token,
        ]);
    }

    /**
     * A WhatsApp price list the guest came from, if it is still valid (WA-01/02).
     */
    public static function usablePriceList(mixed $token): ?PriceList
    {
        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            return null;
        }

        $priceList = PriceList::query()->where('token', $token)->first();

        return $priceList?->isUsable() ? $priceList : null;
    }

    public function store(ReservationFormRequest $request, CreateReservation $create, FinanceSettings $finance): RedirectResponse
    {
        $type = AccommodationType::query()->findOrFail($request->integer('accommodation_type_id'));
        $stay = new StayDates($request->string('arrival')->value(), $request->string('departure')->value());
        $method = PaymentMethod::from($request->string('payment_method')->value());

        if ($method === PaymentMethod::Eft && $finance->bankDetails() === null) {
            return back()->withInput()->withErrors(['payment_method' => 'EFT is not available yet. Please choose another way to pay, or contact us.']);
        }

        $priceList = self::usablePriceList($request->input('price_list'));

        try {
            $booking = $create->handle(new ReservationRequest(
                type: $type,
                stay: $stay,
                adults: $request->integer('adults'),
                children: $request->integer('children'),
                firstName: $request->string('first_name')->trim()->value(),
                lastName: $request->string('last_name')->trim()->value(),
                email: $request->string('email')->value(),
                phone: $request->string('phone')->value(),
                source: $priceList !== null ? BookingSource::WhatsApp : BookingSource::Website,
                status: BookingStatus::Hold,
                preferredPaymentMethod: $method,
                extraQuantities: $request->extraQuantities(),
                specialRequests: $request->validated('special_requests'),
                termsAccepted: true,
                marketingConsent: $request->boolean('marketing_consent'),
            ));
        } catch (RoomUnavailableException $exception) {
            return redirect()
                ->route('availability', ['arrival' => $stay->arrivalString(), 'departure' => $stay->departureString(), 'adults' => $request->integer('adults'), 'children' => $request->integer('children')])
                ->withErrors(['arrival' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['adults' => $exception->getMessage()]);
        }

        $priceList?->forceFill(['booking_id' => $booking->id])->save();

        if ($booking->lead_guest_email) {
            try {
                Mail::to($booking->lead_guest_email)->send(new BookingReceived($booking));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('booking.show', $booking->public_token);
    }

    public function show(string $token): View
    {
        $booking = $this->findByToken($token);

        return view('site.booking', [
            'booking' => $booking,
            'invoice' => $booking->currentInvoice(),
            'methods' => PaymentMethod::onlineOrder(),
            'bank' => app(FinanceSettings::class)->bankDetails(),
        ]);
    }

    public static function findByToken(string $token): Booking
    {
        return Booking::query()
            ->where('public_token', $token)
            ->with(['activeBookingRooms.accommodationType', 'extras', 'guest', 'payments', 'receipts'])
            ->firstOrFail();
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
