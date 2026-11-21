<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Actions\Channels\ImportChannelReservation;
use App\Actions\Inventory\BlockRoom;
use App\Actions\Inventory\ReleaseRoomBlock;
use App\Enums\RoomBlockReason;
use App\Exceptions\RoomUnavailableException;
use App\Models\Channel;
use App\Models\ChannelException;
use App\Models\ChannelSyncRun;
use App\Models\IcalEvent;
use App\Models\IcalLink;
use App\Models\Setting;
use App\Support\StayDates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * CHN-01/02/04: runs channel syncs and records every attempt. A channel only counts as synced
 * when a connected driver succeeded; the null driver is logged as "not connected" so nobody is
 * told a sync happened when it didn't.
 */
class ChannelSync
{
    public function __construct(
        private readonly ChannelDrivers $drivers,
        private readonly ImportChannelReservation $import,
        private readonly IcalCalendar $ical,
        private readonly BlockRoom $blockRoom,
        private readonly ReleaseRoomBlock $releaseBlock,
    ) {}

    public function syncAll(): int
    {
        $runs = 0;

        Channel::query()->where('is_enabled', true)->each(function (Channel $channel) use (&$runs): void {
            $this->sync($channel);
            $runs++;

            if (self::icalFetchEnabled()) {
                $channel->icalLinks()->whereNotNull('import_url')->each(fn (IcalLink $link) => $this->fetchIcal($link));
            }
        });

        return $runs;
    }

    public function sync(Channel $channel): ChannelSyncRun
    {
        $run = ChannelSyncRun::query()->create(['channel_id' => $channel->id, 'operation' => 'reservations', 'status' => 'ok', 'started_at' => now()]);
        $channel->forceFill(['last_attempt_at' => now()])->save();

        try {
            $driver = $this->drivers->for($channel);

            if (! $driver->isConnected()) {
                $run->forceFill(['status' => 'not_connected', 'message' => 'No channel manager is connected (PEND-07/08). Nothing was synced.', 'finished_at' => now()])->save();

                return $run;
            }

            $since = $channel->last_successful_sync_at?->toImmutable()->subMinutes(15) ?? CarbonImmutable::now()->subDays(30);
            $count = 0;

            foreach ($driver->fetchReservations($channel, $since) as $reservation) {
                $this->import->handle($channel, $reservation);
                $count++;
            }

            $run->forceFill(['items' => $count, 'finished_at' => now()])->save();
            $channel->forceFill(['last_successful_sync_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $run->forceFill(['status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 1000), 'finished_at' => now()])->save();
            $channel->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
        }

        return $run;
    }

    /**
     * CHN-02: apply a channel's iCal feed for one room. New events block the room; removed or
     * moved events release it. A clash with one of our bookings goes to the exception queue.
     *
     * @return array{added: int, removed: int, clashes: int}
     */
    public function importIcal(IcalLink $link, string $ics): array
    {
        $link->loadMissing(['room', 'channel']);
        $events = collect($this->ical->parse($ics))->keyBy('uid');
        $counts = ['added' => 0, 'removed' => 0, 'clashes' => 0];
        $today = CarbonImmutable::today('Africa/Johannesburg');

        foreach ($link->events()->whereNull('removed_at')->get() as $known) {
            $incoming = $events->get($known->uid);
            $moved = $incoming !== null && ($incoming['starts_on']->toDateString() !== $known->starts_on->toDateString() || $incoming['ends_on']->toDateString() !== $known->ends_on->toDateString());

            if ($incoming === null || $moved) {
                if ($known->block !== null) {
                    $this->releaseBlock->handle($known->block);
                }

                $known->forceFill(['removed_at' => now(), 'room_block_id' => null])->save();
                $counts['removed']++;

                if ($moved) {
                    $known->forceFill(['uid' => $known->uid.'#moved-'.$known->id])->save();
                }
            }
        }

        foreach ($events as $event) {
            if ($event['ends_on']->lessThanOrEqualTo($today) || $link->events()->where('uid', $event['uid'])->whereNull('removed_at')->exists()) {
                continue;
            }

            $record = IcalEvent::query()->updateOrCreate(
                ['ical_link_id' => $link->id, 'uid' => $event['uid']],
                ['starts_on' => $event['starts_on']->toDateString(), 'ends_on' => $event['ends_on']->toDateString(), 'summary' => $event['summary'], 'removed_at' => null, 'room_block_id' => null],
            );

            try {
                $block = $this->blockRoom->handle(
                    $link->room,
                    new StayDates($event['starts_on']->max($today), $event['ends_on']),
                    RoomBlockReason::Channel,
                    "Booked on {$link->channel->name} (iCal)",
                );
                $record->forceFill(['room_block_id' => $block->id])->save();
                $counts['added']++;
            } catch (RoomUnavailableException) {
                $counts['clashes']++;
                $summary = "{$link->channel->name} calendar shows {$link->room->name} taken {$event['starts_on']->format('j M')}–{$event['ends_on']->format('j M')}, but we have it booked too";

                if (! ChannelException::query()->open()->where('room_id', $link->room_id)->where('kind', ChannelException::ICAL_CLASH)->where('summary', $summary)->exists()) {
                    ChannelException::query()->create([
                        'channel_id' => $link->channel_id,
                        'room_id' => $link->room_id,
                        'kind' => ChannelException::ICAL_CLASH,
                        'summary' => mb_substr($summary, 0, 255),
                        'details' => 'Possible double booking. Check both calendars and move one of the guests.',
                        'status' => 'open',
                    ]);
                    ImportChannelReservation::alert($summary);
                }
            }
        }

        $link->forceFill(['last_imported_at' => now(), 'last_error' => null])->save();

        ChannelSyncRun::query()->create([
            'channel_id' => $link->channel_id,
            'ical_link_id' => $link->id,
            'operation' => 'ical_import',
            'status' => 'ok',
            'items' => $counts['added'] + $counts['removed'],
            'message' => "{$link->room->name}: {$counts['added']} added, {$counts['removed']} removed, {$counts['clashes']} clash(es).",
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $counts;
    }

    /**
     * Fetches a feed over HTTPS. Off until approved (`channels.ical_fetch_enabled`); until then
     * staff paste or upload the calendar file.
     */
    public function fetchIcal(IcalLink $link): void
    {
        if (! self::icalFetchEnabled()) {
            throw new RuntimeException('Fetching calendars from channels is switched off (channels.ical_fetch_enabled).');
        }

        try {
            if (! str_starts_with((string) $link->import_url, 'https://')) {
                throw new RuntimeException('Only https:// calendar links are allowed.');
            }

            $response = Http::timeout(20)->accept('text/calendar')->get((string) $link->import_url)->throw();
            $this->importIcal($link, $response->body());
        } catch (Throwable $exception) {
            $link->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
            ChannelSyncRun::query()->create([
                'channel_id' => $link->channel_id, 'ical_link_id' => $link->id, 'operation' => 'ical_import', 'status' => 'failed',
                'message' => mb_substr($exception->getMessage(), 0, 1000), 'started_at' => now(), 'finished_at' => now(),
            ]);
        }
    }

    public static function icalFetchEnabled(): bool
    {
        return in_array(mb_strtolower((string) Setting::get('channels.ical_fetch_enabled', 'no')), ['yes', 'true', '1', 'on'], true);
    }
}
