<?php

declare(strict_types=1);

namespace App\Services\Leisure;

use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceResourceType;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Which start times can still be booked (LSR-02/03): inside opening hours, after the minimum
 * notice, within capacity, and with one free resource of every type the service needs.
 */
class ServiceAvailability
{
    /**
     * @return list<CarbonImmutable>
     */
    public function slots(Service $service, CarbonImmutable $date, int $partySize, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $hours = $service->hoursOn($date);

        if (! $service->is_active || $hours === null || $partySize < 1 || $partySize > $service->max_party_size) {
            return [];
        }

        $slots = [];
        $earliest = $now->addMinutes($service->min_notice_minutes);

        for ($start = $hours['open']; $start->addMinutes($service->duration_minutes)->lessThanOrEqualTo($hours['close']); $start = $start->addMinutes($service->slot_interval_minutes)) {
            if ($start->greaterThanOrEqualTo($earliest) && $this->isAvailable($service, $start, $partySize)) {
                $slots[] = $start;
            }
        }

        return $slots;
    }

    /**
     * Is this exact start time a valid slot on its day (aligned to the slot interval, inside hours)?
     */
    public function isValidStart(Service $service, CarbonImmutable $start): bool
    {
        $hours = $service->hoursOn($start);

        if ($hours === null) {
            return false;
        }

        $local = $start->setTimezone('Africa/Johannesburg');
        $offset = (int) $hours['open']->diffInMinutes($local, false);

        return $offset >= 0
            && $offset % $service->slot_interval_minutes === 0
            && $local->addMinutes($service->duration_minutes)->lessThanOrEqualTo($hours['close']);
    }

    public function isAvailable(Service $service, CarbonImmutable $start, int $partySize, ?int $ignoreBookingId = null): bool
    {
        if ($partySize < 1 || $partySize > $service->max_party_size) {
            return false;
        }

        $end = $start->addMinutes($service->duration_minutes);

        if ($service->capacity !== null) {
            $overlapping = ServiceBooking::query()
                ->where('service_id', $service->id)
                ->where('status', ServiceBookingStatus::Confirmed)
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId));

            $used = $service->capacity_unit === Service::CAPACITY_BOOKINGS
                ? $overlapping->count() + 1
                : (int) $overlapping->sum('party_size') + $partySize;

            if ($used > $service->capacity) {
                return false;
            }
        }

        foreach ($service->requiredResourceTypes() as $type) {
            if ($this->freeResources($type, $start, $end)->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, ServiceResource>
     */
    public function freeResources(ServiceResourceType $type, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return ServiceResource::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->whereDoesntHave('allocations', fn ($query) => $query->whereNull('released_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start))
            ->orderBy('id')
            ->get();
    }
}
