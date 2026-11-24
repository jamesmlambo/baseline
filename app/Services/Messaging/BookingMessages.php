<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Models\Booking;
use App\Models\Message;
use App\Models\PriceList;
use App\Models\User;
use App\Services\Portal\PortalAccess;

/**
 * The automated guest messages (MSG-03), each with an idempotency key so retries and
 * repeated scheduler runs never message a guest twice.
 */
class BookingMessages
{
    public function __construct(private readonly Messenger $messenger) {}

    public function bookingReceived(Booking $booking): ?Message
    {
        return $this->toGuest('booking_received', $booking, [
            'hold_deadline' => $booking->hold_expires_at?->setTimezone('Africa/Johannesburg')->format('D j M, H:i'),
            'amount_due' => $booking->deposit_due_cents->format(),
        ]);
    }

    public function holdReminder(Booking $booking): ?Message
    {
        return $this->toGuest('hold_reminder', $booking, [
            'hold_deadline' => $booking->hold_expires_at?->setTimezone('Africa/Johannesburg')->format('H:i'),
        ]);
    }

    public function paymentConfirmed(Booking $booking, ?MessageChannel $channel = null): ?Message
    {
        return $this->toGuest('payment_confirmed', $booking, channel: $channel);
    }

    public function preArrival(Booking $booking): ?Message
    {
        return $this->toGuest('pre_arrival', $booking);
    }

    public function departureDay(Booking $booking): ?Message
    {
        return $this->toGuest('departure_day', $booking);
    }

    /**
     * GST-05: the portal link, sent when the guest checks in (they are only "in house" from then).
     */
    public function portalWelcome(Booking $booking): ?Message
    {
        return $this->toGuest('portal_welcome', $booking, ['portal_link' => app(PortalAccess::class)->linkFor($booking)]);
    }

    public function farewell(Booking $booking): ?Message
    {
        return $this->toGuest('farewell', $booking);
    }

    public function feedbackRequest(Booking $booking): ?Message
    {
        return $this->toGuest('feedback_request', $booking, ['feedback_link' => route('contact')]);
    }

    public function priceList(PriceList $priceList, ?User $by = null): ?Message
    {
        return $this->messenger->send(
            'price_list',
            $priceList->customer_phone,
            $this->priceListVariables($priceList),
            'price_list:'.$priceList->id,
            sentBy: $by,
        );
    }

    public function priceListFollowUp(PriceList $priceList): ?Message
    {
        return $this->messenger->send(
            'price_list_follow_up',
            $priceList->customer_phone,
            $this->priceListVariables($priceList),
            'price_list_follow_up:'.$priceList->id,
        );
    }

    /**
     * @param  array<string, string|int|null>  $extra
     */
    private function toGuest(string $key, Booking $booking, array $extra = [], ?MessageChannel $channel = null): ?Message
    {
        return $this->messenger->send(
            $key,
            $booking->lead_guest_phone,
            [
                'guest_name' => $booking->guest?->first_name ?? strtok($booking->lead_guest_name, ' '),
                'reference' => $booking->reference,
                'arrival_date' => $booking->arrival_date->format('D j M Y'),
                'departure_date' => $booking->departure_date->format('D j M Y'),
                'booking_link' => $booking->public_token ? route('booking.show', $booking->public_token) : null,
                ...$extra,
            ],
            $key.':'.$booking->id,
            booking: $booking,
            channel: $channel,
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private function priceListVariables(PriceList $priceList): array
    {
        return [
            'customer_name' => $priceList->customer_name ?: 'there',
            'arrival_date' => $priceList->arrival_date->format('D j M'),
            'departure_date' => $priceList->departure_date->format('D j M Y'),
            'price_list_link' => $priceList->url(),
            'expires_at' => $priceList->expires_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i'),
        ];
    }
}
