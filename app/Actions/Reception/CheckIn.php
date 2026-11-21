<?php

declare(strict_types=1);

namespace App\Actions\Reception;

use App\Enums\BookingStatus;
use App\Enums\FrontDeskLogType;
use App\Models\Booking;
use App\Models\FrontDeskLog;
use App\Models\GuestRegisterEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\Bookings\BookingStateMachine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Checks a guest in (REC-03): confirmed bookings only, on or after the arrival date,
 * with the guest register captured (REC-04) and, optionally, the key handover logged (REC-05).
 */
class CheckIn
{
    public function __construct(private readonly BookingStateMachine $stateMachine) {}

    /**
     * @param  array{full_name?: string, id_type?: ?string, id_number?: ?string, nationality?: ?string, home_address?: ?string, vehicle_registration?: ?string, number_of_guests?: ?int}  $register
     */
    public function handle(Booking $booking, User $by, array $register, bool $keyIssued = false, ?string $keyLabel = null): Booking
    {
        if (! $by->can('update', $booking)) {
            throw new AuthorizationException('You cannot check guests in.');
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new InvalidArgumentException("Only confirmed bookings can be checked in. This booking is {$booking->status->label()}.");
        }

        if ($booking->arrival_date->isFuture()) {
            throw new InvalidArgumentException('This guest is only due on '.$booking->arrival_date->format('l j F').'.');
        }

        $this->assertRegisterComplete($register);

        return DB::transaction(function () use ($booking, $by, $register, $keyIssued, $keyLabel): Booking {
            GuestRegisterEntry::query()->updateOrCreate(['booking_id' => $booking->id], [
                'guest_id' => $booking->guest_id,
                'full_name' => $register['full_name'] ?? $booking->lead_guest_name,
                'id_type' => $register['id_type'] ?? null,
                'id_number' => $register['id_number'] ?? null,
                'nationality' => $register['nationality'] ?? null,
                'home_address' => $register['home_address'] ?? null,
                'vehicle_registration' => $register['vehicle_registration'] ?? null,
                'number_of_guests' => $register['number_of_guests'] ?? null,
                'recorded_by' => $by->id,
            ]);

            $this->stateMachine->transition($booking, BookingStatus::CheckedIn, 'Checked in at reception', $by);

            $booking->forceFill([
                'checked_in_at' => now(),
                'checked_in_by' => $by->id,
                'reception_code' => $booking->reception_code ?? Str::random(32),
            ])->save();

            if ($keyIssued) {
                FrontDeskLog::query()->create([
                    'booking_id' => $booking->id,
                    'room_id' => $booking->activeBookingRooms()->value('room_id'),
                    'type' => FrontDeskLogType::KeyIssued,
                    'item' => $keyLabel ?: 'Room key',
                    'recorded_by' => $by->id,
                ]);
            }

            return $booking->refresh();
        });
    }

    /**
     * Required register fields are a setting until the legal requirement is verified (REC-04).
     *
     * @return list<string>
     */
    public static function requiredRegisterFields(): array
    {
        return array_values(array_filter((array) Setting::get('register.required_fields', ['full_name'])));
    }

    /**
     * @param  array<string, mixed>  $register
     */
    private function assertRegisterComplete(array $register): void
    {
        $missing = array_filter(self::requiredRegisterFields(), fn (string $field): bool => blank($register[$field] ?? null));

        if ($missing !== []) {
            throw new InvalidArgumentException('Please complete the guest register: '.implode(', ', array_map(fn (string $field): string => str_replace('_', ' ', $field), $missing)).'.');
        }
    }
}
