<?php

declare(strict_types=1);

namespace App\Services\Leisure;

use App\Actions\Reception\AddCharge;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceCategory;
use App\Enums\ServiceResourceType;
use App\Enums\StaffRole;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingResource;
use App\Models\User;
use App\Services\Finance\DocumentIssuer;
use App\Services\Payments\PaymentLedger;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Leisure bookings (LSR-01..06): book, cancel, complete, no-show and take payment.
 *
 * - Capacity is checked under a lock on the service row; therapists and rooms are held with
 *   rows the database refuses to overlap, so two people can never get the same slot (LSR-03).
 * - In-house guests may charge eligible services to their room; the charge is added once, when
 *   the service has been given (LSR-05). Everyone else pays at reception first.
 * - Guests may cancel only before the service's cut-off; staff may cancel any time with a reason.
 */
class ServiceBookings
{
    public function __construct(
        private readonly ServiceAvailability $availability,
        private readonly AddCharge $addCharge,
        private readonly DocumentIssuer $documents,
        private readonly PaymentLedger $payments,
        private readonly KitchenTickets $kitchen,
    ) {}

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, notes?: ?string}  $customer
     */
    public function book(Service $service, CarbonImmutable $start, int $partySize, array $customer, ?Booking $stay, bool $chargeToRoom, string $source, ?User $by = null, bool $indemnityAccepted = false): ServiceBooking
    {
        if ($source === ServiceBooking::SOURCE_RECEPTION && ($by === null || ! $this->isFrontDesk($by))) {
            throw new AuthorizationException('Only reception, a manager or the owner can take leisure bookings.');
        }

        if ($source === ServiceBooking::SOURCE_PORTAL && ($stay === null || ! $service->bookable_by_guests)) {
            throw new InvalidArgumentException('This service can only be booked at reception.');
        }

        if (! $service->is_active) {
            throw new InvalidArgumentException('This service is not available.');
        }

        if (trim($customer['name'] ?? '') === '') {
            throw new InvalidArgumentException('Enter the name of the person booking.');
        }

        if ($partySize < 1 || $partySize > $service->max_party_size) {
            throw new InvalidArgumentException("Bookings are for 1 to {$service->max_party_size} people.");
        }

        if (! $this->availability->isValidStart($service, $start)) {
            throw new InvalidArgumentException('Choose one of the times offered.');
        }

        if ($start->lessThan(CarbonImmutable::now()->addMinutes($service->min_notice_minutes))) {
            throw new InvalidArgumentException('That time is too soon to book.');
        }

        if ($chargeToRoom) {
            if ($stay === null || $stay->status !== BookingStatus::CheckedIn) {
                throw new InvalidArgumentException('Only guests who are checked in can charge to their room.');
            }

            if (! $service->charge_to_room_allowed) {
                throw new InvalidArgumentException('This service cannot be charged to the room.');
            }
        }

        $needsIndemnity = $service->requires_indemnity && Features::enabled(Features::INDEMNITY);

        if ($needsIndemnity && ! $indemnityAccepted) {
            throw new InvalidArgumentException('Please read and accept the safety notice first.');
        }

        $end = $start->addMinutes($service->duration_minutes);

        $booking = DB::transaction(function () use ($service, $start, $end, $partySize, $customer, $stay, $chargeToRoom, $source, $by, $needsIndemnity): ServiceBooking {
            Service::query()->whereKey($service->id)->lockForUpdate()->first();

            if (! $this->availability->isAvailable($service, $start, $partySize)) {
                throw new InvalidArgumentException('Sorry, that time is no longer available.');
            }

            $booking = ServiceBooking::query()->create([
                'reference' => 'LS-'.Str::upper(Str::random(6)),
                'service_id' => $service->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'party_size' => $partySize,
                'customer_name' => trim($customer['name']),
                'customer_phone' => $customer['phone'] ?? null,
                'customer_email' => $customer['email'] ?? null,
                'notes' => $customer['notes'] ?? null,
                'booking_id' => $stay?->id,
                'status' => ServiceBookingStatus::Confirmed,
                'total_cents' => $service->priceFor($partySize),
                'charge_to_room' => $chargeToRoom,
                'indemnity_accepted_at' => $needsIndemnity ? now() : null,
                'source' => $source,
                'created_by' => $by?->id,
            ]);

            foreach ($service->requiredResourceTypes() as $type) {
                $this->hold($booking, $type, $start, $end);
            }

            return $booking;
        });

        if ($service->category === ServiceCategory::Meal) {
            $this->kitchen->forServiceBooking($booking);
        }

        return $booking;
    }

    public function cancel(ServiceBooking $booking, string $reason, ?User $by = null): ServiceBooking
    {
        if ($by !== null && ! $this->isFrontDesk($by)) {
            throw new AuthorizationException('You cannot cancel leisure bookings.');
        }

        if ($booking->status !== ServiceBookingStatus::Confirmed) {
            throw new InvalidArgumentException('Only upcoming bookings can be cancelled.');
        }

        if ($booking->isPaidOrBilled()) {
            throw new InvalidArgumentException('This booking has been paid or billed. Refund or credit it through the payment first.');
        }

        if ($by === null && CarbonImmutable::now()->greaterThan($booking->starts_at->subHours($booking->service->cancellation_cutoff_hours))) {
            throw new InvalidArgumentException("Bookings can be cancelled up to {$booking->service->cancellation_cutoff_hours} hours before. Please contact reception.");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Give a reason.');
        }

        DB::transaction(function () use ($booking, $reason): void {
            // A payment taken at the same moment must win: re-check under the same row lock takePayment uses.
            $locked = ServiceBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ServiceBookingStatus::Confirmed || $locked->invoice_id !== null) {
                throw new InvalidArgumentException('This booking has just been paid or closed. Refresh and check it.');
            }

            $booking->forceFill(['status' => ServiceBookingStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => mb_substr(trim($reason), 0, 255)])->save();
            $this->release($booking);
        });

        $this->kitchen->cancelFor($booking);

        return $booking;
    }

    /**
     * The service was given. A room charge is added now, exactly once.
     */
    public function complete(ServiceBooking $booking, User $by): ServiceBooking
    {
        if (! $this->isFrontDesk($by)) {
            throw new AuthorizationException('You cannot complete leisure bookings.');
        }

        return DB::transaction(function () use ($booking, $by): ServiceBooking {
            /** @var ServiceBooking $locked */
            $locked = ServiceBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ServiceBookingStatus::Confirmed) {
                throw new InvalidArgumentException('This booking is already closed.');
            }

            if (! $locked->charge_to_room && $locked->total_cents->cents > 0 && $locked->invoice_id === null) {
                throw new InvalidArgumentException('Take payment first.');
            }

            if ($locked->charge_to_room && $locked->invoice_id === null && $locked->total_cents->cents > 0) {
                $invoice = $this->addCharge->handle(
                    $locked->stay,
                    "{$locked->service->name}, ".$locked->starts_at->setTimezone('Africa/Johannesburg')->format('j M H:i')." ({$locked->party_size} ".($locked->party_size === 1 ? 'person' : 'people').')',
                    1,
                    $locked->total_cents,
                    $by,
                );
                $locked->forceFill(['invoice_id' => $invoice->id]);
            }

            $locked->forceFill(['status' => ServiceBookingStatus::Completed, 'completed_at' => now()])->save();
            $this->release($locked, keepHistory: true);

            return $locked;
        });
    }

    /**
     * Fees for no-shows are not charged until the client sets a policy (PEND-05).
     */
    public function noShow(ServiceBooking $booking, User $by): ServiceBooking
    {
        if (! $this->isFrontDesk($by)) {
            throw new AuthorizationException('You cannot update leisure bookings.');
        }

        if ($booking->status !== ServiceBookingStatus::Confirmed) {
            throw new InvalidArgumentException('This booking is already closed.');
        }

        if ($booking->starts_at->isFuture()) {
            throw new InvalidArgumentException('A booking can only be a no-show after its start time.');
        }

        DB::transaction(function () use ($booking): void {
            $booking->forceFill(['status' => ServiceBookingStatus::NoShow])->save();
            $this->release($booking);
        });

        $this->kitchen->cancelFor($booking);

        return $booking;
    }

    /**
     * LSR-02: payment at reception for someone not charging to a room. Issues an invoice and,
     * for cash, a receipt straight away.
     */
    public function takePayment(ServiceBooking $booking, PaymentMethod $method, User $by, ?string $bankReference = null): ServiceBooking
    {
        if (! $this->isFrontDesk($by)) {
            throw new AuthorizationException('You cannot take payments.');
        }

        if ($booking->charge_to_room) {
            throw new InvalidArgumentException('This booking goes on the room bill.');
        }

        if ($booking->invoice_id !== null) {
            throw new InvalidArgumentException('Payment has already been taken.');
        }

        if ($booking->status === ServiceBookingStatus::Cancelled || $booking->total_cents->cents === 0) {
            throw new InvalidArgumentException('Nothing to pay.');
        }

        return DB::transaction(function () use ($booking, $method, $by, $bankReference): ServiceBooking {
            // Two clicks at once must not create two invoices and two payments: re-check under a row lock.
            /** @var ServiceBooking $locked */
            $locked = ServiceBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice_id !== null) {
                throw new InvalidArgumentException('Payment has already been taken.');
            }

            if ($locked->status === ServiceBookingStatus::Cancelled) {
                throw new InvalidArgumentException('Nothing to pay.');
            }

            $unit = $booking->service->pricing === Service::PER_BOOKING ? $booking->total_cents : $booking->service->price_cents;
            $quantity = $booking->service->pricing === Service::PER_BOOKING ? 1 : $booking->party_size;

            $invoice = $this->documents->invoiceForWalkIn($booking->customer_name, [[
                'description' => "{$booking->service->name}, ".$booking->starts_at->setTimezone('Africa/Johannesburg')->format('j M Y H:i')." ({$booking->reference})",
                'quantity' => $quantity,
                'unit' => $unit,
                'total' => $booking->total_cents,
            ]], $by);

            $payment = $this->payments->recordWalkIn($invoice, $method, $by, $bankReference);

            $booking->forceFill(['invoice_id' => $invoice->id, 'payment_id' => $payment->id])->save();

            return $booking;
        });
    }

    private function hold(ServiceBooking $booking, ServiceResourceType $type, CarbonImmutable $start, CarbonImmutable $end): void
    {
        foreach ($this->availability->freeResources($type, $start, $end) as $resource) {
            try {
                DB::transaction(fn () => ServiceBookingResource::query()->create([
                    'service_booking_id' => $booking->id,
                    'service_resource_id' => $resource->id,
                    'starts_at' => $start,
                    'ends_at' => $end,
                ]));

                return;
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23P01') {
                    throw $exception;
                }
            }
        }

        throw new InvalidArgumentException('Sorry, no '.mb_strtolower($type->label()).' is free at that time any more.');
    }

    /**
     * Frees therapists and rooms. Completed bookings keep their allocation as history.
     */
    private function release(ServiceBooking $booking, bool $keepHistory = false): void
    {
        if ($keepHistory) {
            return;
        }

        $booking->resourceAllocations()->whereNull('released_at')->update(['released_at' => now(), 'updated_at' => now()]);
    }

    private function isFrontDesk(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value]);
    }
}
