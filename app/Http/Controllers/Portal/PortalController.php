<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Reception\CheckOut;
use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use App\Enums\PaymentRecordStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\OutstandingBalanceException;
use App\Http\Controllers\Controller;
use App\Models\AuthorizedPhone;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\GuestRequest;
use App\Models\MenuCategoryRule;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\Setting;
use App\Services\Portal\GuestRequests;
use App\Services\Portal\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Guest portal pages (GST-07). Everything is scoped to the signed-in stay: a guest can only ever
 * see their own requests, messages and bill (GST-06).
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly PortalAccess $access,
        private readonly GuestRequests $requests,
    ) {}

    public function home(Request $request): View
    {
        $booking = $this->booking($request);
        $seenAt = now()->setTimestamp((int) $request->session()->get('portal_messages_seen_at', 0));

        return view('portal.home', [
            'booking' => $booking,
            'info' => self::stayInformation(),
            'openRequests' => $booking->guestRequests()->open()->count(),
            'unreadReplies' => $booking->guestMessages()->where('direction', GuestMessage::OUT)->where('created_at', '>', $seenAt)->count(),
        ]);
    }

    public function requests(Request $request): View
    {
        $booking = $this->booking($request);

        return view('portal.requests', [
            'booking' => $booking,
            'types' => GuestRequestType::simpleOptions(),
            'requests' => $booking->guestRequests()->get(),
        ]);
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(GuestRequestType::simpleOptions()))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->requests->submit($this->booking($request), GuestRequestType::from($validated['type']), $validated['details'] ?? null);

        return redirect()->route('portal.requests')->with('status', 'Request sent to reception.');
    }

    public function menu(Request $request): View
    {
        return view('portal.menu', [
            'booking' => $this->booking($request),
            'menu' => MenuItem::query()->available()->get()->groupBy('category'),
            'orderingHours' => MenuCategoryRule::query()->get()->keyBy('category'),
        ]);
    }

    public function order(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'charge_to_room' => ['nullable', 'boolean'],
        ]);

        try {
            $this->requests->orderMeal($this->booking($request), $validated['quantities'], $validated['notes'] ?? null, $request->boolean('charge_to_room', true));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['quantities' => $exception->getMessage()]);
        }

        return redirect()->route('portal.requests')->with('status', 'Order sent. Reception will confirm it shortly.');
    }

    public function messages(Request $request): View
    {
        $booking = $this->booking($request);
        $request->session()->put('portal_messages_seen_at', now()->timestamp);

        return view('portal.messages', ['booking' => $booking, 'messages' => $booking->guestMessages()->get()]);
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->requests->messageReception($this->booking($request), $validated['body']);

        return redirect()->route('portal.messages');
    }

    public function bill(Request $request): View
    {
        $booking = $this->booking($request);

        return view('portal.bill', [
            'booking' => $booking,
            'invoices' => $booking->invoices()->whereNull('voided_at')->with('lines')->get(),
            'payments' => $booking->payments()->where('status', PaymentRecordStatus::Succeeded)->get(),
            'balance' => $booking->balanceDue(),
            'awaitingVerification' => $booking->payments()->where('status', PaymentRecordStatus::PendingVerification)->exists(),
        ]);
    }

    /**
     * CHK-01/02: check out from the portal when nothing is owed; otherwise reception is told and
     * the guest is shown how to settle the bill.
     */
    public function checkout(Request $request, CheckOut $checkOut): RedirectResponse
    {
        $booking = $this->booking($request);

        try {
            $checkOut->byGuest($booking);
        } catch (OutstandingBalanceException|InvalidArgumentException $exception) {
            GuestRequest::query()->firstOrCreate(
                ['booking_id' => $booking->id, 'type' => GuestRequestType::Checkout, 'status' => GuestRequestStatus::New],
                ['room_id' => $booking->activeBookingRooms()->value('room_id'), 'details' => 'Guest asked to check out: '.$exception->getMessage()],
            );

            return redirect()->route('portal.bill')->with('status', $exception->getMessage().' Reception has been told you would like to check out.');
        }

        $this->access->forget($request);

        return redirect()->route('portal.start')->with('status', 'You are checked out. Thank you for staying with us — safe travels!');
    }

    public function review(Request $request): View
    {
        $booking = $this->booking($request);

        return view('portal.review', ['booking' => $booking, 'review' => $booking->review]);
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $booking = $this->booking($request);

        if ($booking->review()->exists()) {
            return redirect()->route('portal.review');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'display_name' => ['nullable', 'string', 'max:80'],
            'consent_to_publish' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:6144'],
        ]);

        $review = Review::query()->create([
            'booking_id' => $booking->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'display_name' => $validated['display_name'] ?? null,
            'consent_to_publish' => $request->boolean('consent_to_publish'),
            'status' => ReviewStatus::Pending,
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $review->addMedia($photo)->toMediaCollection('photos', 'local');
        }

        return redirect()->route('portal.review')->with('status', 'Thank you for your review!');
    }

    public function phones(Request $request): View
    {
        $booking = $this->booking($request);

        return view('portal.phones', [
            'booking' => $booking,
            'isBooker' => $this->access->isBooker($request, $booking),
            'phones' => $booking->authorizedPhones()->active()->get(),
        ]);
    }

    /**
     * GST-04: the verified booker lets another guest in the room use the portal.
     */
    public function addPhone(Request $request): RedirectResponse
    {
        $booking = $this->booking($request);
        abort_unless($this->access->isBooker($request, $booking), 403);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
        ], ['phone.regex' => 'Please enter a valid mobile number.']);

        $hash = Guest::lookupHash('phone', $validated['phone']);

        if (! $booking->authorizedPhones()->active()->where('phone_hash', $hash)->exists()) {
            AuthorizedPhone::query()->create([
                'booking_id' => $booking->id,
                'label' => $validated['label'] ?? null,
                'phone' => Guest::normalisePhone($validated['phone']),
                'phone_hash' => $hash,
                'added_by' => 'booker',
            ]);
        }

        return redirect()->route('portal.phones')->with('status', 'Added. They can now sign in with the QR code in the room.');
    }

    public function removePhone(Request $request, int $phone): RedirectResponse
    {
        $booking = $this->booking($request);
        abort_unless($this->access->isBooker($request, $booking), 403);

        $booking->authorizedPhones()->active()->whereKey($phone)->firstOrFail()->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('portal.phones')->with('status', 'Removed.');
    }

    /**
     * Stay information shown on the portal home page. Nothing is invented: empty settings are hidden.
     *
     * @return array<string, ?string>
     */
    public static function stayInformation(): array
    {
        return [
            'welcome' => Setting::get('portal.welcome'),
            'wifi_name' => Setting::get('portal.wifi_name'),
            'wifi_password' => Setting::get('portal.wifi_password'),
            'facility_hours' => Setting::get('portal.facility_hours'),
            'emergency' => Setting::get('portal.emergency_contacts'),
            'check_out_time' => Setting::get('booking.check_out_time'),
        ];
    }

    private function booking(Request $request): Booking
    {
        return $request->attributes->get('portal_booking');
    }
}
