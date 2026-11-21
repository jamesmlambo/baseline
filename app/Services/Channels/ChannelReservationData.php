<?php

declare(strict_types=1);

namespace App\Services\Channels;

use Carbon\CarbonImmutable;

/**
 * One reservation as a channel reports it.
 */
final readonly class ChannelReservationData
{
    public const NEW = 'new';

    public const MODIFIED = 'modified';

    public const CANCELLED = 'cancelled';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalReference,
        public string $status,
        public string $roomCode,
        public CarbonImmutable $arrival,
        public CarbonImmutable $departure,
        public int $adults,
        public int $children,
        public string $guestName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?int $totalCents = null,
        public array $raw = [],
    ) {}

    /**
     * The parts that, if they change, need a person to look (dates, room type, party size).
     *
     * @return array<string, mixed>
     */
    public function stayFingerprint(): array
    {
        return [
            'room_code' => $this->roomCode,
            'arrival' => $this->arrival->toDateString(),
            'departure' => $this->departure->toDateString(),
            'adults' => $this->adults,
            'children' => $this->children,
        ];
    }
}
