<?php

declare(strict_types=1);

namespace App\Services\Leisure;

use App\Enums\GuestRequestStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\StaffRole;
use App\Models\GuestRequest;
use App\Models\KitchenTicket;
use App\Models\MenuItem;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Kitchen tickets (LSR-04). One per room-service order or meal booking, with the allergens
 * copied from the menu. Kitchen marks Preparing → Ready; delivery is confirmed when reception
 * closes the guest's order (which is also when it is billed).
 */
class KitchenTickets
{
    public function forMealOrder(GuestRequest $request): KitchenTicket
    {
        $items = collect($request->items ?? []);
        $allergens = MenuItem::query()
            ->whereIn('id', $items->pluck('menu_item_id')->filter()->all())
            ->whereNotNull('allergens')
            ->get()
            ->map(fn (MenuItem $item): string => "{$item->name}: {$item->allergens}")
            ->implode('; ');

        $request->loadMissing(['room', 'booking']);

        return KitchenTicket::query()->firstOrCreate(['guest_request_id' => $request->id], [
            'room_id' => $request->room_id,
            'for_label' => $request->room?->name ?? $request->booking?->lead_guest_name ?? 'Guest',
            'items' => $items->map(fn (array $item): array => ['name' => $item['name'], 'quantity' => (int) $item['quantity']])->values()->all(),
            'allergens' => $allergens !== '' ? $allergens : null,
            'notes' => $request->details,
            'status' => KitchenTicketStatus::New,
        ]);
    }

    public function forServiceBooking(ServiceBooking $booking): KitchenTicket
    {
        return KitchenTicket::query()->firstOrCreate(['service_booking_id' => $booking->id], [
            'room_id' => $booking->stay?->activeBookingRooms()->value('room_id'),
            'for_label' => "{$booking->customer_name} ({$booking->reference})",
            'items' => [['name' => $booking->service->name, 'quantity' => $booking->party_size]],
            'notes' => $booking->notes,
            'wanted_at' => $booking->starts_at,
            'status' => KitchenTicketStatus::New,
        ]);
    }

    public function advance(KitchenTicket $ticket, KitchenTicketStatus $to, User $by): KitchenTicket
    {
        if (! $by->is_active || ! $by->hasAnyRole([StaffRole::KitchenStaff->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value])) {
            throw new AuthorizationException('Only kitchen staff, reception or a manager can update kitchen tickets.');
        }

        $allowed = match ($ticket->status) {
            KitchenTicketStatus::New => [KitchenTicketStatus::Preparing, KitchenTicketStatus::Ready],
            KitchenTicketStatus::Preparing => [KitchenTicketStatus::Ready],
            KitchenTicketStatus::Ready => [KitchenTicketStatus::Delivered],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("A {$ticket->status->label()} ticket cannot become {$to->label()}.");
        }

        $ticket->forceFill([
            'status' => $to,
            'preparing_at' => $to === KitchenTicketStatus::Preparing ? now() : $ticket->preparing_at,
            'ready_at' => $to === KitchenTicketStatus::Ready ? now() : $ticket->ready_at,
            'delivered_at' => $to === KitchenTicketStatus::Delivered ? now() : $ticket->delivered_at,
        ])->save();

        // The guest sees "On its way" once the kitchen has started.
        $request = $ticket->guestRequest;

        if ($request !== null && $request->status === GuestRequestStatus::New) {
            $request->forceFill(['status' => GuestRequestStatus::Acknowledged, 'acknowledged_at' => now()])->save();
        }

        return $ticket;
    }

    public function deliveredFor(GuestRequest $request): void
    {
        KitchenTicket::query()->where('guest_request_id', $request->id)->open()
            ->update(['status' => KitchenTicketStatus::Delivered, 'delivered_at' => now(), 'updated_at' => now()]);
    }

    public function cancelFor(GuestRequest|ServiceBooking $source): void
    {
        KitchenTicket::query()
            ->where($source instanceof GuestRequest ? 'guest_request_id' : 'service_booking_id', $source->id)
            ->open()
            ->update(['status' => KitchenTicketStatus::Cancelled, 'updated_at' => now()]);
    }
}
