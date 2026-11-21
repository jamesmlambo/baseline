<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\BookingStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Enums\MessageStatus;
use App\Models\AuthorizedPhone;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Message;
use App\Models\PortalCode;
use App\Models\Room;
use App\Models\User;
use App\Services\Messaging\MessageProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Throwable;

/**
 * Guest-portal sign-in and stay sessions (GST-01..06).
 *
 * - The room QR identifies a room only. A visitor proves they belong to the stay with a
 *   6-digit code sent to the booking phone or a number the booker authorised.
 * - Responses never reveal whether a room is occupied or a number is on a booking.
 * - A session is bound to the stay and room, and ends at checkout, room move, QR rotation
 *   or when staff end portal access.
 */
class PortalAccess
{
    public const CODE_TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const MAX_SENDS = 3;

    public const SEND_WINDOW_MINUTES = 15;

    public const PUMPING_ALERT_PER_HOUR = 30;

    private const SESSION_KEY = 'portal';

    public function __construct(private readonly MessageProviderManager $providers) {}

    public function activeStayForRoom(Room $room): ?Booking
    {
        return Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->whereHas('activeBookingRooms', fn ($query) => $query->where('room_id', $room->id))
            ->first();
    }

    /**
     * GST-02: send a code if — and only if — the number belongs to the stay in this room.
     * The caller always shows the same reply, whatever happened here.
     */
    public function requestCode(Room $room, ?string $phone, ?string $ip): void
    {
        $booking = $this->activeStayForRoom($room);
        $phoneHash = Guest::lookupHash('phone', $phone);

        if ($booking === null || $phoneHash === null || ! $this->phoneBelongsToStay($booking, $phoneHash)) {
            return;
        }

        $recentSends = PortalCode::query()
            ->where('booking_id', $booking->id)
            ->where('created_at', '>=', now()->subMinutes(self::SEND_WINDOW_MINUTES))
            ->count();

        if ($recentSends >= self::MAX_SENDS) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $record = PortalCode::query()->create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'phone_hash' => $phoneHash,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'ip_hash' => $ip !== null ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ]);

        $this->sendCode($booking, (string) Guest::normalisePhone($phone), $code, $record);
        $this->watchForPumping();
    }

    /**
     * GST-02: check a code. Five wrong tries and the code is dead.
     */
    public function verifyCode(Room $room, ?string $phone, string $code, Request $request): bool
    {
        $booking = $this->activeStayForRoom($room);
        $phoneHash = Guest::lookupHash('phone', $phone);

        if ($booking === null || $phoneHash === null) {
            return false;
        }

        $record = PortalCode::query()
            ->where('booking_id', $booking->id)
            ->where('room_id', $room->id)
            ->where('phone_hash', $phoneHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null || $record->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check(trim($code), $record->code_hash)) {
            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        $this->startSession($request, $booking, $room, $phoneHash, 'qr');

        return true;
    }

    /**
     * GST-05: a signed link (sent by SMS/WhatsApp to the booking phone) that opens the portal directly.
     */
    public function linkFor(Booking $booking, ?CarbonImmutable $expiresAt = null, string $via = 'link'): string
    {
        $expiresAt ??= $booking->departure_date->setTimezone('Africa/Johannesburg')->setTime(12, 0)->addDay();

        return URL::temporarySignedRoute('portal.link', $expiresAt, ['booking' => $booking->id, 'via' => $via]);
    }

    /**
     * GST-03: reception recovery after an identity check, logged with who did it and why.
     */
    public function receptionLink(Booking $booking, User $by, string $identityCheck): string
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You are not allowed to issue guest-portal links.');
        }

        if ($booking->status !== BookingStatus::CheckedIn) {
            throw new InvalidArgumentException('The guest portal is only available during a checked-in stay.');
        }

        activity('guest_portal')
            ->performedOn($booking)
            ->causedBy($by)
            ->event('portal_recovery_link')
            ->withProperties(['identity_check' => $identityCheck])
            ->log('Reception issued a guest-portal access link after an identity check');

        return $this->linkFor($booking, now()->toImmutable()->addMinutes(30), 'reception');
    }

    /**
     * Signs every device out of this stay's portal. Guests can sign in again with a new code.
     */
    public function endAccess(Booking $booking, User $by, string $reason): void
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You are not allowed to end guest-portal access.');
        }

        $booking->forceFill(['portal_revoked_at' => now()])->save();

        activity('guest_portal')
            ->performedOn($booking)
            ->causedBy($by)
            ->event('portal_access_ended')
            ->withProperties(['reason' => $reason])
            ->log('Guest-portal access ended for all devices');
    }

    /**
     * Replaces a room's QR code. Old printed codes stop working and QR-started sessions end.
     */
    public function rotateRoomQr(Room $room, User $by): string
    {
        if (! $by->can('update', $room)) {
            throw new AuthorizationException('You are not allowed to replace room QR codes.');
        }

        $token = $room->rotatePortalToken();

        activity('guest_portal')
            ->performedOn($room)
            ->causedBy($by)
            ->event('room_qr_replaced')
            ->log('Room QR code replaced');

        return $token;
    }

    public function startFromLink(Request $request, Booking $booking, string $via): bool
    {
        $room = $booking->activeBookingRooms()->with('room')->first()?->room;

        if ($booking->status !== BookingStatus::CheckedIn || $room === null) {
            return false;
        }

        $phoneHash = $via === 'reception' ? null : Guest::lookupHash('phone', $booking->lead_guest_phone);
        $this->startSession($request, $booking, $room, $phoneHash, $via);

        return true;
    }

    /**
     * The booking for this visitor's portal session, or null if the session is missing or no longer valid (GST-06).
     */
    public function currentStay(Request $request): ?Booking
    {
        $session = $request->session()->get(self::SESSION_KEY);

        if (! is_array($session)) {
            return null;
        }

        $booking = Booking::query()->find($session['booking_id'] ?? null);
        $valid = $booking !== null
            && $booking->status === BookingStatus::CheckedIn
            && ($booking->portal_revoked_at === null || $booking->portal_revoked_at->timestamp < ($session['verified_at'] ?? 0))
            && $booking->activeBookingRooms()->where('room_id', $session['room_id'] ?? 0)->exists();

        if ($valid && ($session['via'] ?? null) === 'qr') {
            $room = Room::query()->find($session['room_id']);
            $valid = $room !== null && hash_equals((string) ($session['room_token_hash'] ?? ''), hash('sha256', (string) $room->portal_token))
                && $this->phoneBelongsToStay($booking, (string) ($session['phone_hash'] ?? ''));
        }

        if (! $valid) {
            $this->forget($request);

            return null;
        }

        return $booking;
    }

    /**
     * GST-04: only the person who made the booking may add or remove other guests' numbers.
     */
    public function isBooker(Request $request, Booking $booking): bool
    {
        $phoneHash = $request->session()->get(self::SESSION_KEY.'.phone_hash');

        return $phoneHash !== null && hash_equals((string) Guest::lookupHash('phone', $booking->lead_guest_phone), (string) $phoneHash);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function phoneBelongsToStay(Booking $booking, string $phoneHash): bool
    {
        if (hash_equals((string) Guest::lookupHash('phone', $booking->lead_guest_phone), $phoneHash)) {
            return true;
        }

        return AuthorizedPhone::query()->active()->where('booking_id', $booking->id)->where('phone_hash', $phoneHash)->exists();
    }

    private function startSession(Request $request, Booking $booking, Room $room, ?string $phoneHash, string $via): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'room_token_hash' => hash('sha256', (string) $room->portal_token),
            'phone_hash' => $phoneHash,
            'via' => $via,
            'verified_at' => now()->timestamp,
        ]);
    }

    /**
     * The code goes straight to the SMS provider. The message log keeps a copy with the
     * code blanked out, so nobody can read it afterwards (GST-03).
     */
    private function sendCode(Booking $booking, string $to, string $code, PortalCode $record): void
    {
        $text = "Your Baseline Lodge guest portal code is {$code}. It expires in ".self::CODE_TTL_MINUTES.' minutes. Never share it.';
        $message = Message::query()->create([
            'channel' => MessageChannel::Sms,
            'classification' => MessageClassification::Operational,
            'recipient' => $to,
            'recipient_hash' => (string) Guest::lookupHash('phone', $to),
            'body' => str_replace($code, '••••••', $text),
            'status' => MessageStatus::Queued,
            'idempotency_key' => 'portal_code:'.$record->id,
            'booking_id' => $booking->id,
            'guest_id' => $booking->guest_id,
            'cost_cents' => 0,
        ]);

        $provider = $this->providers->for(MessageChannel::Sms);

        try {
            $receipt = $provider->send($to, $text);
            $message->forceFill([
                'status' => $receipt->delivered ? MessageStatus::Delivered : MessageStatus::Sent,
                'provider' => $provider->name(),
                'provider_message_id' => $receipt->providerMessageId,
                'cost_cents' => $receipt->cost,
                'attempts' => 1,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $message->forceFill(['status' => MessageStatus::Failed, 'attempts' => 1, 'last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
        }
    }

    /**
     * GST-02: an unusual number of codes in an hour may be SMS pumping (fraud that runs up SMS bills).
     */
    private function watchForPumping(): void
    {
        $lastHour = PortalCode::query()->where('created_at', '>=', now()->subHour())->count();

        if ($lastHour === self::PUMPING_ALERT_PER_HOUR) {
            Log::warning("Guest portal: {$lastHour} access codes sent in the last hour. Possible SMS pumping — check Front desk › Messages.");
            activity('guest_portal')->event('possible_sms_pumping')->withProperties(['codes_last_hour' => $lastHour])->log('Unusually many guest-portal codes sent');
        }
    }
}
