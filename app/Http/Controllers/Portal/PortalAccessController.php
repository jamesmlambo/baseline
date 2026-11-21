<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Portal\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signing in to the guest portal (GST-01..05). Every page and reply here is identical whether
 * or not the room is occupied or the number is on a booking (anti-enumeration).
 */
class PortalAccessController extends Controller
{
    public function __construct(private readonly PortalAccess $access) {}

    public function start(Request $request): View|RedirectResponse
    {
        if ($this->access->currentStay($request) !== null) {
            return redirect()->route('portal.home');
        }

        return view('portal.start');
    }

    public function room(string $token): View
    {
        $this->findRoom($token);

        return view('portal.sign-in', ['token' => $token]);
    }

    public function sendCode(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/']], [
            'phone.regex' => 'Please enter a valid mobile number.',
        ]);

        $this->access->requestCode($this->findRoom($token), $validated['phone'], $request->ip());

        $request->session()->put('portal_phone_attempt', $validated['phone']);

        return redirect()->route('portal.room.verify', $token);
    }

    public function verifyForm(Request $request, string $token): View|RedirectResponse
    {
        $this->findRoom($token);

        if (! $request->session()->has('portal_phone_attempt')) {
            return redirect()->route('portal.room', $token);
        }

        return view('portal.verify', ['token' => $token]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'The code has 6 digits.']);
        $phone = $request->session()->get('portal_phone_attempt');

        if (! is_string($phone) || ! $this->access->verifyCode($this->findRoom($token), $phone, $validated['code'], $request)) {
            return back()->withErrors(['code' => 'That code is not right, or it has expired. You can ask for a new one.']);
        }

        $request->session()->forget('portal_phone_attempt');

        return redirect()->route('portal.home');
    }

    /**
     * GST-05 / GST-03: signed link from an SMS or WhatsApp, or issued by reception after an ID check.
     */
    public function link(Request $request, int $booking): RedirectResponse
    {
        $stay = Booking::query()->find($booking);
        $via = $request->query('via') === 'reception' ? 'reception' : 'link';

        if ($stay === null || ! $this->access->startFromLink($request, $stay, $via)) {
            return redirect()->route('portal.start')->with('status', 'This link no longer works. Scan the QR code in your room to sign in.');
        }

        return redirect()->route('portal.home');
    }

    public function signOut(Request $request): RedirectResponse
    {
        $this->access->forget($request);

        return redirect()->route('portal.start')->with('status', 'You have signed out of the guest portal.');
    }

    private function findRoom(string $token): Room
    {
        return Room::query()->where('portal_token', $token)->firstOrFail();
    }
}
