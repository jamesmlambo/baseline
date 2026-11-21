<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PriceList;
use App\Models\Setting;
use App\Services\Messaging\BookingMessages;
use Illuminate\Support\Carbon;

/**
 * Time-based messages (MSG-03), run every five minutes by the scheduler. Each message has an
 * idempotency key, so running this as often as we like never sends anything twice.
 *
 * @phpstan-type Counts array{hold_reminders: int, pre_arrival: int, departure_day: int, feedback: int, price_list_follow_ups: int}
 */
class RunMessageAutomations
{
    public const PRE_ARRIVAL_DAYS = 2;

    public const MORNING_HOUR = 8;

    public function __construct(private readonly BookingMessages $messages) {}

    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        $now = Carbon::now('Africa/Johannesburg');
        $counts = ['hold_reminders' => 0, 'pre_arrival' => 0, 'departure_day' => 0, 'feedback' => 0, 'price_list_follow_ups' => 0];

        // Unpaid-invoice reminder: holds ending in the next 10 minutes that were made at least 10 minutes ago.
        Booking::query()
            ->where('status', BookingStatus::Hold)
            ->whereBetween('hold_expires_at', [now(), now()->addMinutes(10)])
            ->where('created_at', '<=', now()->subMinutes(10))
            ->each(function (Booking $booking) use (&$counts): void {
                $counts['hold_reminders'] += $this->messages->holdReminder($booking) ? 1 : 0;
            });

        if ($now->hour >= self::MORNING_HOUR) {
            Booking::query()
                ->where('status', BookingStatus::Confirmed)
                ->where('arrival_date', $now->copy()->addDays(self::PRE_ARRIVAL_DAYS)->toDateString())
                ->each(function (Booking $booking) use (&$counts): void {
                    $counts['pre_arrival'] += $this->messages->preArrival($booking) ? 1 : 0;
                });

            Booking::query()
                ->where('status', BookingStatus::CheckedIn)
                ->where('departure_date', $now->toDateString())
                ->each(function (Booking $booking) use (&$counts): void {
                    $counts['departure_day'] += $this->messages->departureDay($booking) ? 1 : 0;
                });

            if (Setting::get('messaging.feedback_request_enabled') === 'yes') {
                Booking::query()
                    ->where('status', BookingStatus::CheckedOut)
                    ->whereBetween('checked_out_at', [$now->copy()->subDays(2), $now->copy()->subDay()])
                    ->each(function (Booking $booking) use (&$counts): void {
                        $counts['feedback'] += $this->messages->feedbackRequest($booking) ? 1 : 0;
                    });
            }
        }

        $followUpHours = (int) Setting::get('price_lists.follow_up_hours', 4);

        if ($followUpHours > 0) {
            PriceList::query()
                ->whereNull('booking_id')
                ->whereNull('revoked_at')
                ->whereNull('followed_up_at')
                ->whereNotNull('customer_phone')
                ->where('created_at', '<=', now()->subHours($followUpHours))
                ->where('expires_at', '>', now())
                ->each(function (PriceList $priceList) use (&$counts): void {
                    if ($priceList->isUsable() && $this->messages->priceListFollowUp($priceList)) {
                        $priceList->forceFill(['followed_up_at' => now()])->save();
                        $counts['price_list_follow_ups']++;
                    }
                });
        }

        return $counts;
    }
}
