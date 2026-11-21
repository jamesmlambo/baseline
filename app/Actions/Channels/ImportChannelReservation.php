<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Actions\Bookings\Data\BookingRequest;
use App\Actions\Bookings\Data\RoomRequest;
use App\Actions\Bookings\PlaceBooking;
use App\Enums\BookingStatus;
use App\Enums\StaffRole;
use App\Exceptions\RoomUnavailableException;
use App\Models\Channel;
use App\Models\ChannelException;
use App\Models\ChannelReservation;
use App\Models\ChannelRoomType;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\StaffAlert;
use App\Services\Bookings\BookingStateMachine;
use App\Services\Channels\ChannelReservationData;
use App\Services\Finance\DocumentIssuer;
use App\Services\Payments\PaymentLedger;
use App\Support\Money;
use App\Support\StayDates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * CHN-03 / DEC-02: an OTA reservation is placed through the same booking engine as every other
 * source, with a physical room assigned at once. If that can't be done safely — no room free,
 * unknown room code, changed dates, a cancellation we can't apply — it goes to the exception
 * queue and reception, managers and the owner are alerted. Never overbooked; safe to repeat.
 */
class ImportChannelReservation
{
    public function __construct(
        private readonly PlaceBooking $placeBooking,
        private readonly BookingStateMachine $stateMachine,
        private readonly PaymentLedger $payments,
        private readonly DocumentIssuer $documents,
    ) {}

    public function handle(Channel $channel, ChannelReservationData $data): ChannelReservation
    {
        return DB::transaction(function () use ($channel, $data): ChannelReservation {
            $existing = ChannelReservation::query()
                ->where('channel_id', $channel->id)
                ->where('external_reference', $data->externalReference)
                ->lockForUpdate()
                ->first();

            $reservation = $existing ?? new ChannelReservation(['channel_id' => $channel->id, 'external_reference' => $data->externalReference]);
            $previous = $reservation->payload['stay'] ?? null;

            $reservation->fill([
                'payload' => ['stay' => $data->stayFingerprint(), 'guest' => $data->guestName, 'raw' => $data->raw],
                'channel_total_cents' => $data->totalCents,
                'last_seen_at' => now(),
            ]);

            if ($data->status === ChannelReservationData::CANCELLED) {
                return $this->cancel($channel, $reservation, $data);
            }

            // New, or still waiting in the exception queue: try to place it (a room may have come free).
            if ($existing === null || $reservation->booking_id === null && $reservation->status === ChannelReservation::EXCEPTION) {
                return $this->place($channel, $reservation, $data);
            }

            if ($previous !== $data->stayFingerprint()) {
                $reservation->status = ChannelReservation::MODIFIED;
                $reservation->save();
                $this->raise($channel, $reservation, ChannelException::MODIFIED, "{$channel->name} {$data->externalReference} was changed", 'New stay: '.json_encode($data->stayFingerprint()).'. Previous: '.json_encode($previous).'. Update the booking by hand (move dates/room), then resolve.');

                return $reservation;
            }

            $reservation->save();

            return $reservation;
        });
    }

    private function place(Channel $channel, ChannelReservation $reservation, ChannelReservationData $data): ChannelReservation
    {
        $mapping = ChannelRoomType::query()->with('accommodationType')->where('channel_id', $channel->id)->where('external_code', $data->roomCode)->first();

        if ($mapping === null) {
            $reservation->status = ChannelReservation::EXCEPTION;
            $reservation->save();
            $this->raise($channel, $reservation, ChannelException::UNMAPPED, "{$channel->name} {$data->externalReference}: room code “{$data->roomCode}” is not linked to a room type", 'Link the code under Channels, then place the booking by hand.');

            return $reservation;
        }

        try {
            $booking = DB::transaction(fn () => $this->placeBooking->handle(new BookingRequest(
                stay: new StayDates($data->arrival, $data->departure),
                rooms: [new RoomRequest($mapping->accommodationType, $data->adults, $data->children)],
                leadGuestName: $data->guestName,
                source: $channel->bookingSource(),
                status: BookingStatus::Confirmed,
                leadGuestEmail: Guest::normaliseEmail($data->email),
                leadGuestPhone: Guest::normalisePhone($data->phone),
                specialRequests: "Imported from {$channel->name} ({$data->externalReference}).",
                externalReference: $data->externalReference,
            )));
        } catch (RoomUnavailableException $exception) {
            $reservation->status = ChannelReservation::EXCEPTION;
            $reservation->save();
            $this->raise(
                $channel,
                $reservation,
                ChannelException::NO_ROOM,
                "No room free for {$channel->name} {$data->externalReference} ({$data->arrival->format('j M')}–{$data->departure->format('j M')})",
                "{$mapping->accommodationType->name} for {$data->guestName}. Nothing was overbooked. Find a room or contact the channel and the guest.",
            );

            return $reservation;
        }

        // The guest agreed the channel's price: use it for the stay and the invoice when it is given.
        if ($data->totalCents !== null && $data->totalCents > 0) {
            $total = Money::cents($data->totalCents);
            $booking->activeBookingRooms()->update(['accommodation_total_cents' => $total->cents]);
            $booking->forceFill(['total_cents' => $total])->save();
            $this->documents->invoiceForCharges($booking, [[
                'description' => "Accommodation, {$data->arrival->format('j M')}–{$data->departure->format('j M Y')} (booked on {$channel->name}, {$data->externalReference})",
                'quantity' => 1,
                'unit' => $total,
                'total' => $total,
            ]]);
        } else {
            $this->documents->invoiceForBooking($booking);
        }

        $reservation->forceFill(['booking_id' => $booking->id, 'status' => ChannelReservation::BOOKED])->save();

        // A queued reservation that now has a room closes its "no room" or "unknown code" alert.
        ChannelException::query()->open()->where('channel_reservation_id', $reservation->id)->whereIn('kind', [ChannelException::NO_ROOM, ChannelException::UNMAPPED])
            ->update(['status' => 'resolved', 'resolution' => 'Placed automatically on a later import', 'resolved_at' => now(), 'updated_at' => now()]);

        return $reservation;
    }

    private function cancel(Channel $channel, ChannelReservation $reservation, ChannelReservationData $data): ChannelReservation
    {
        $booking = $reservation->booking;

        if ($booking !== null && in_array($booking->status, [BookingStatus::Hold, BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            try {
                $this->stateMachine->transition($booking, BookingStatus::Cancelled, "Cancelled on {$channel->name}", null);
                $this->payments->sync($booking);
            } catch (Throwable $exception) {
                $reservation->status = ChannelReservation::EXCEPTION;
                $reservation->save();
                $this->raise($channel, $reservation, ChannelException::CANCEL_FAILED, "{$channel->name} cancelled {$data->externalReference}, but it could not be applied", $exception->getMessage());

                return $reservation;
            }
        } elseif ($booking !== null && $booking->status !== BookingStatus::Cancelled) {
            $reservation->status = ChannelReservation::EXCEPTION;
            $reservation->save();
            $this->raise($channel, $reservation, ChannelException::CANCEL_FAILED, "{$channel->name} cancelled {$data->externalReference}, but the guest is {$booking->status->label()}", 'Check with the guest and the channel.');

            return $reservation;
        }

        $reservation->status = ChannelReservation::CANCELLED;
        $reservation->save();

        ChannelException::query()->open()->where('channel_reservation_id', $reservation->id)
            ->update(['status' => 'resolved', 'resolution' => 'Cancelled on the channel', 'resolved_at' => now(), 'updated_at' => now()]);

        return $reservation;
    }

    private function raise(Channel $channel, ChannelReservation $reservation, string $kind, string $summary, ?string $details): void
    {
        $exists = ChannelException::query()->open()->where('channel_reservation_id', $reservation->id)->where('kind', $kind)->exists();

        if ($exists) {
            return;
        }

        ChannelException::query()->create([
            'channel_id' => $channel->id,
            'channel_reservation_id' => $reservation->id,
            'kind' => $kind,
            'summary' => mb_substr($summary, 0, 255),
            'details' => $details,
            'status' => 'open',
        ]);

        self::alert($summary);
    }

    public static function alert(string $summary): void
    {
        DB::afterCommit(fn () => Notification::send(
            User::query()->role([StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value])->where('is_active', true)->get(),
            new StaffAlert('Channel booking needs attention', $summary, url('/admin/channel-exceptions'), urgent: true),
        ));
    }
}
