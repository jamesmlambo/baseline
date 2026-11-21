<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Guest;
use App\Models\PriceList;
use App\Models\Setting;
use App\Models\User;
use App\Services\Messaging\BookingMessages;
use App\Support\StayDates;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reception creates a secure, expiring price-list link for a WhatsApp enquiry (WA-01) and,
 * if a phone number is given, sends it through the messaging system.
 */
class CreatePriceList
{
    public function __construct(private readonly BookingMessages $messages) {}

    public function handle(StayDates $stay, int $adults, int $children, User $by, ?string $customerName = null, ?string $customerPhone = null, bool $send = true): PriceList
    {
        if (! $by->can('create', PriceList::class)) {
            throw new AuthorizationException('You cannot create price lists.');
        }

        if ($stay->arrival->isPast() && ! $stay->arrival->isToday()) {
            throw new InvalidArgumentException('The arrival date has already passed.');
        }

        if ($adults < 1) {
            throw new InvalidArgumentException('At least one adult is required.');
        }

        $priceList = PriceList::query()->create([
            'token' => Str::random(48),
            'arrival_date' => $stay->arrivalString(),
            'departure_date' => $stay->departureString(),
            'adults' => $adults,
            'children' => $children,
            'customer_name' => $customerName ? trim($customerName) : null,
            'customer_phone' => Guest::normalisePhone($customerPhone),
            'expires_at' => now()->addHours(max(1, (int) Setting::get('price_lists.valid_hours', 24))),
            'created_by' => $by->id,
        ]);

        if ($send && $priceList->customer_phone !== null) {
            $this->messages->priceList($priceList, $by);
        }

        return $priceList;
    }

    /**
     * WhatsApp click-to-chat link with the message ready to send from reception's own WhatsApp.
     * Works without any WhatsApp API: staff press send themselves.
     */
    public static function clickToChatUrl(PriceList $priceList): ?string
    {
        $phone = ltrim((string) $priceList->customer_phone, '+');

        $text = sprintf(
            'Hi %s, here are our available rooms and prices for %s to %s: %s (link valid until %s). Rooms are only reserved once you book. We will never ask for card details by WhatsApp.',
            $priceList->customer_name ?: 'there',
            $priceList->arrival_date->format('D j M'),
            $priceList->departure_date->format('D j M Y'),
            $priceList->url(),
            $priceList->expires_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i'),
        );

        return 'https://wa.me/'.($phone !== '' ? $phone : '').'?text='.rawurlencode($text);
    }
}
