<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Actions\Reception\AddCharge;
use App\Enums\GuestRequestStatus;
use App\Enums\GuestRequestType;
use App\Models\Booking;
use App\Models\GuestMessage;
use App\Models\GuestRequest;
use App\Models\MenuCategoryRule;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Leisure\KitchenTickets;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Guest requests, meal orders and reception messages from the portal (GST-07), and how staff
 * acknowledge and complete them. A meal charged to the room is billed when it is delivered.
 */
class GuestRequests
{
    public const MAX_ITEMS_PER_ORDER = 20;

    public function __construct(
        private readonly AddCharge $addCharge,
        private readonly KitchenTickets $kitchen,
    ) {}

    public function submit(Booking $booking, GuestRequestType $type, ?string $details): GuestRequest
    {
        if (! array_key_exists($type->value, GuestRequestType::simpleOptions())) {
            throw new InvalidArgumentException('Unknown request type.');
        }

        return GuestRequest::query()->create([
            'booking_id' => $booking->id,
            'room_id' => $booking->activeBookingRooms()->value('room_id'),
            'type' => $type,
            'status' => GuestRequestStatus::New,
            'details' => $details !== null ? mb_substr(trim($details), 0, 1000) : null,
        ]);
    }

    /**
     * @param  array<int, int>  $quantities  menu_item_id => quantity
     */
    public function orderMeal(Booking $booking, array $quantities, ?string $notes, bool $chargeToRoom): GuestRequest
    {
        $quantities = array_filter(array_map('intval', $quantities), fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            throw new InvalidArgumentException('Choose at least one item.');
        }

        if (array_sum($quantities) > self::MAX_ITEMS_PER_ORDER) {
            throw new InvalidArgumentException('For orders of more than '.self::MAX_ITEMS_PER_ORDER.' items, please message reception.');
        }

        $menu = MenuItem::query()->where('is_available', true)->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        if ($menu->count() !== count($quantities)) {
            throw new InvalidArgumentException('One of the items is no longer available. Please check the menu again.');
        }

        // LSR-04: order cut-off times per menu category.
        $closed = MenuCategoryRule::closedAmong($menu->pluck('category')->unique()->values()->all(), CarbonImmutable::now());

        if ($closed !== []) {
            $rule = array_values($closed)[0];

            throw new InvalidArgumentException("{$rule->category} orders are taken between {$rule->windowLabel()}.".($rule->note ? " {$rule->note}" : ''));
        }

        $items = [];
        $total = Money::zero();

        foreach ($quantities as $id => $quantity) {
            $item = $menu[$id];
            $lineTotal = $item->price_cents->multiply($quantity);
            $total = $total->add($lineTotal);
            $items[] = ['menu_item_id' => $item->id, 'name' => $item->name, 'quantity' => $quantity, 'unit_cents' => $item->price_cents->cents, 'total_cents' => $lineTotal->cents];
        }

        $request = GuestRequest::query()->create([
            'booking_id' => $booking->id,
            'room_id' => $booking->activeBookingRooms()->value('room_id'),
            'type' => GuestRequestType::Meal,
            'status' => GuestRequestStatus::New,
            'details' => $notes !== null ? mb_substr(trim($notes), 0, 500) : null,
            'items' => $items,
            'total_cents' => $total,
            'charge_to_room' => $chargeToRoom,
        ]);

        $this->kitchen->forMealOrder($request);

        return $request;
    }

    public function messageReception(Booking $booking, string $body): GuestMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Please type a message.');
        }

        return GuestMessage::query()->create([
            'booking_id' => $booking->id,
            'direction' => GuestMessage::IN,
            'body' => mb_substr($body, 0, 2000),
        ]);
    }

    public function reply(Booking $booking, string $body, User $by): GuestMessage
    {
        $this->authorize($by, $booking);

        GuestMessage::query()->where('booking_id', $booking->id)->where('direction', GuestMessage::IN)->whereNull('read_at')->update(['read_at' => now()]);

        return GuestMessage::query()->create([
            'booking_id' => $booking->id,
            'direction' => GuestMessage::OUT,
            'body' => mb_substr(trim($body), 0, 2000),
            'staff_id' => $by->id,
            'read_at' => now(),
        ]);
    }

    public function acknowledge(GuestRequest $request, User $by): GuestRequest
    {
        $this->authorize($by, $request->booking);

        if ($request->status !== GuestRequestStatus::New) {
            return $request;
        }

        $request->forceFill(['status' => GuestRequestStatus::Acknowledged, 'acknowledged_by' => $by->id, 'acknowledged_at' => now()])->save();

        return $request;
    }

    /**
     * Marks a request done. A meal charged to the room is billed now, exactly once.
     */
    public function complete(GuestRequest $request, User $by): GuestRequest
    {
        $this->authorize($by, $request->booking);

        return DB::transaction(function () use ($request, $by): GuestRequest {
            /** @var GuestRequest $locked */
            $locked = GuestRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isOpen()) {
                return $locked;
            }

            $invoiceId = null;

            if ($locked->type === GuestRequestType::Meal && $locked->charge_to_room && $locked->total_cents->cents > 0) {
                $invoiceId = $this->addCharge->handle(
                    $locked->booking,
                    'Room service: '.collect($locked->items)->map(fn (array $item): string => $item['quantity'].' × '.$item['name'])->implode(', '),
                    1,
                    $locked->total_cents,
                    $by,
                )->id;
            }

            $locked->forceFill([
                'status' => GuestRequestStatus::Done,
                'acknowledged_by' => $locked->acknowledged_by ?? $by->id,
                'acknowledged_at' => $locked->acknowledged_at ?? now(),
                'completed_by' => $by->id,
                'completed_at' => now(),
                'invoice_id' => $invoiceId,
            ])->save();

            $this->kitchen->deliveredFor($locked);

            return $locked;
        });
    }

    public function cancel(GuestRequest $request, ?User $by = null): GuestRequest
    {
        if ($by !== null) {
            $this->authorize($by, $request->booking);
        }

        if ($request->status === GuestRequestStatus::New) {
            $request->forceFill(['status' => GuestRequestStatus::Cancelled])->save();
            $this->kitchen->cancelFor($request);
        }

        return $request;
    }

    private function authorize(User $user, Booking $booking): void
    {
        if (! $user->can('update', $booking)) {
            throw new AuthorizationException('You cannot handle guest requests.');
        }
    }
}
