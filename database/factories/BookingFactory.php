<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates a bare booking row without room assignments. Use PlaceBooking to get a real,
 * inventory-claiming booking in tests.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $arrival = now()->addDays(10)->startOfDay();

        return [
            'reference' => 'BL-'.Str::upper(Str::random(6)),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'source' => BookingSource::Phone,
            'arrival_date' => $arrival->toDateString(),
            'departure_date' => $arrival->copy()->addDays(2)->toDateString(),
            'lead_guest_name' => fake()->name(),
            'lead_guest_email' => fake()->safeEmail(),
            'lead_guest_phone' => '+27820000000',
            'total_cents' => 0,
        ];
    }
}
