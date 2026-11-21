<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Models\IcalLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * CHN-02: reading and writing iCal (RFC 5545) for one room. Dates only: no prices, no guest
 * details, no room types. Nights blocked because of this same link's import are left out of the
 * export, so a calendar never echoes back to where it came from.
 */
class IcalCalendar
{
    public function export(IcalLink $link): string
    {
        $link->loadMissing(['room', 'channel']);
        $own = DB::table('ical_events')->where('ical_link_id', $link->id)->whereNotNull('room_block_id')->pluck('room_block_id')->all();

        $nights = DB::table('room_nights')
            ->where('room_id', $link->room_id)
            ->whereNull('released_at')
            ->where('night_date', '>=', today()->subDays(7)->toDateString())
            ->when($own !== [], fn ($query) => $query->where(fn ($inner) => $inner->whereNull('room_block_id')->orWhereNotIn('room_block_id', $own)))
            ->orderBy('night_date')
            ->pluck('night_date')
            ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date))
            ->unique(fn (CarbonImmutable $date): string => $date->toDateString())
            ->values();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Baseline Lodge//Availability//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($this->ranges($nights->all()) as [$start, $end]) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.md5($link->id.'|'.$start->toDateString()).'@baseline-lodge';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$end->format('Ymd');
            $lines[] = 'SUMMARY:Not available';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * All-day events from a feed: uid, start date and end date (exclusive), summary.
     *
     * @return list<array{uid: string, starts_on: CarbonImmutable, ends_on: CarbonImmutable, summary: ?string}>
     */
    public function parse(string $ics): array
    {
        // Unfold folded lines (RFC 5545 §3.1).
        $text = preg_replace("/\r?\n[ \t]/", '', str_replace("\r\n", "\n", $ics)) ?? '';
        $events = [];

        if (! preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $text, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            $field = function (string $name) use ($block): ?string {
                return preg_match('/^'.$name.'(?:;[^:\n]*)?:(.*)$/m', $block, $match) ? trim($match[1]) : null;
            };

            $uid = $field('UID');
            $start = $this->date($field('DTSTART'));
            $end = $this->date($field('DTEND')) ?? $start?->addDay();

            if ($uid === null || $start === null || $end === null || $end->lessThanOrEqualTo($start)) {
                continue;
            }

            $events[] = ['uid' => mb_substr($uid, 0, 255), 'starts_on' => $start, 'ends_on' => $end, 'summary' => ($summary = $field('SUMMARY')) !== null ? mb_substr($summary, 0, 255) : null];
        }

        return $events;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || ! preg_match('/^(\d{8})/', $value, $match)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd', $match[1], 'Africa/Johannesburg')?->startOfDay();
    }

    /**
     * @param  list<CarbonImmutable>  $nights
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function ranges(array $nights): array
    {
        $ranges = [];

        foreach ($nights as $night) {
            $last = array_key_last($ranges);

            if ($last !== null && $ranges[$last][1]->equalTo($night)) {
                $ranges[$last][1] = $night->addDay();
            } else {
                $ranges[] = [$night, $night->addDay()];
            }
        }

        return $ranges;
    }
}
