@php
    /** @var array<string, mixed> $data */
    $rand = fn (?int $cents): string => $cents === null ? '—' : \App\Support\Money::cents($cents)->format();
    $pct = fn (?float $value): string => $value === null ? '—' : number_format($value, 1).' %';
    $rooms = $data['rooms'] ?? [];
    $stays = $data['stays'] ?? [];
    $money = $data['money'] ?? [];
    $balances = $data['balances'] ?? [];
    $ops = $data['operations'] ?? [];
    $marketing = $data['marketing'] ?? [];
    $period = $data['period'] ?? [];
    $sourceLabels = collect(\App\Enums\BookingSource::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]);
    $attendanceLabels = collect(\App\Enums\AttendanceStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]);
    $card = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
    $label = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $value = 'mt-1 text-2xl font-semibold tabular-nums';
@endphp

<div class="space-y-8">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ \Carbon\CarbonImmutable::parse($period['from'] ?? today())->format('j M Y') }}
        @if (($period['from'] ?? null) !== ($period['until'] ?? null)) – {{ \Carbon\CarbonImmutable::parse($period['until'])->format('j M Y') }} @endif
        · {{ $period['nights'] ?? 1 }} {{ ($period['nights'] ?? 1) === 1 ? 'night' : 'nights' }}
        · figures as at {{ \Carbon\CarbonImmutable::parse($period['as_at'] ?? now())->setTimezone('Africa/Johannesburg')->format('j M Y H:i') }}.
        Formulas: <span class="font-mono">docs/REPORT_METRICS.md</span>.
    </p>

    <section aria-labelledby="rooms-h">
        <h2 id="rooms-h" class="text-base font-semibold">Rooms <span class="text-sm font-normal text-gray-500">— by stay date, confirmed/in-house/checked-out bookings only</span></h2>
        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <div class="{{ $card }}"><p class="{{ $label }}">Occupancy</p><p class="{{ $value }}">{{ $pct($rooms['occupancy_percent'] ?? null) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Room-nights sold</p><p class="{{ $value }}">{{ $rooms['nights_sold'] ?? 0 }} <span class="text-sm font-normal text-gray-500">/ {{ $rooms['rooms_available'] ?? 0 }}</span></p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Room revenue</p><p class="{{ $value }}">{{ $rand($rooms['room_revenue_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">ADR</p><p class="{{ $value }}">{{ $rand($rooms['adr_cents'] ?? null) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">RevPAR</p><p class="{{ $value }}">{{ $rand($rooms['revpar_cents'] ?? null) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Nights blocked</p><p class="{{ $value }}">{{ $rooms['nights_blocked'] ?? 0 }}</p></div>
        </div>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            Stays in this period —
            confirmed {{ $stays['confirmed'] ?? 0 }} · pending {{ $stays['pending'] ?? 0 }} · cancelled {{ $stays['cancelled'] ?? 0 }} · no-show {{ $stays['no_show'] ?? 0 }} · expired holds {{ $stays['expired'] ?? 0 }}
        </p>
    </section>

    <section aria-labelledby="money-h">
        <h2 id="money-h" class="text-base font-semibold">Money <span class="text-sm font-normal text-gray-500">— by transaction date</span></h2>
        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <div class="{{ $card }}"><p class="{{ $label }}">Payments received</p><p class="{{ $value }}">{{ $rand($money['payments_received_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Refunds paid</p><p class="{{ $value }}">{{ $rand($money['refunds_paid_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Invoiced</p><p class="{{ $value }}">{{ $rand($money['invoiced_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Credit notes</p><p class="{{ $value }}">{{ $rand($money['credit_notes_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Leisure done</p><p class="{{ $value }}">{{ $rand($money['leisure_completed_cents'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Owed now</p><p class="{{ $value }}">{{ $rand($balances['owed_cents'] ?? 0) }}</p><p class="text-xs text-gray-500">{{ $balances['count'] ?? 0 }} stay(s), as at now</p></div>
        </div>
        @if (! empty($money['payments_by_method_cents']))
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                By method:
                @foreach ($money['payments_by_method_cents'] as $method => $cents)
                    {{ \App\Enums\PaymentMethod::tryFrom($method)?->label() ?? $method }} {{ $rand($cents) }}@if (! $loop->last) · @endif
                @endforeach
                · EFTs awaiting verification: {{ $money['eft_awaiting_verification'] ?? 0 }}
            </p>
        @endif
    </section>

    <section aria-labelledby="ops-h">
        <h2 id="ops-h" class="text-base font-semibold">Operations <span class="text-sm font-normal text-gray-500">— last day of the period, open items as at now</span></h2>
        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <div class="{{ $card }}"><p class="{{ $label }}">Arrivals / departures</p><p class="{{ $value }}">{{ $ops['arrivals'] ?? 0 }} / {{ $ops['departures'] ?? 0 }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">In house now</p><p class="{{ $value }}">{{ $ops['in_house'] ?? 0 }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Rooms ready</p><p class="{{ $value }}">{{ $ops['rooms_by_status']['ready'] ?? 0 }}</p><p class="text-xs text-gray-500">dirty {{ $ops['rooms_by_status']['dirty'] ?? 0 }} · cleaning {{ $ops['rooms_by_status']['cleaning'] ?? 0 }} · to inspect {{ $ops['rooms_by_status']['awaiting_inspection'] ?? 0 }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Inspection score</p><p class="{{ $value }}">{{ $pct($ops['inspection_score_percent'] ?? null) }}</p><p class="text-xs text-gray-500">{{ $ops['inspections_passed'] ?? 0 }} of {{ $ops['inspections_done'] ?? 0 }} passed</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Housekeeping</p><p class="{{ $value }}">{{ $ops['housekeeping_done'] ?? 0 }} done</p><p class="text-xs text-gray-500">{{ $ops['housekeeping_open'] ?? 0 }} open · {{ $ops['tasks_overdue'] ?? 0 }} overdue (all tasks)</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Maintenance</p><p class="{{ $value }}">{{ $ops['maintenance_open'] ?? 0 }} open</p><p class="text-xs text-gray-500">{{ $ops['faults_reported'] ?? 0 }} fault(s) reported</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Complaints</p><p class="{{ $value }}">{{ $ops['complaints_open'] ?? 0 }} open</p><p class="text-xs text-gray-500">{{ $ops['complaints_new'] ?? 0 }} new in period</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Guest requests waiting</p><p class="{{ $value }}">{{ ($ops['guest_requests_open'] ?? 0) + ($ops['guest_messages_unanswered'] ?? 0) }}</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Attendance</p>
                <p class="mt-1 text-sm">
                    @forelse (($ops['attendance'] ?? []) as $status => $count)
                        {{ $attendanceLabels[$status] ?? $status }} {{ $count }}@if (! $loop->last) · @endif
                    @empty
                        No attendance worked out yet
                    @endforelse
                </p>
                <p class="text-xs text-gray-500">{{ $ops['active_employees'] ?? 0 }} active employees</p>
            </div>
            <div class="{{ $card }}"><p class="{{ $label }}">Exceptions</p>
                <p class="mt-1 text-sm">Payments {{ $ops['exceptions']['payments_needing_attention'] ?? 0 }} · Messages failed {{ $ops['exceptions']['messages_failed'] ?? 0 }} · Jobs failed {{ $ops['exceptions']['jobs_failed'] ?? 0 }} · Report deliveries {{ $ops['exceptions']['report_deliveries_failed'] ?? 0 }}</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="mkt-h">
        <h2 id="mkt-h" class="text-base font-semibold">Bookings and marketing <span class="text-sm font-normal text-gray-500">— by the date the booking or enquiry was made</span></h2>
        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="{{ $card }}"><p class="{{ $label }}">Online conversion</p><p class="{{ $value }}">{{ $pct($marketing['online_conversion_percent'] ?? null) }}</p><p class="text-xs text-gray-500">{{ $marketing['online_bookings_confirmed'] ?? 0 }} of {{ $marketing['online_bookings_started'] ?? 0 }} website bookings confirmed</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">WhatsApp price lists</p><p class="{{ $value }}">{{ $pct($marketing['price_list_conversion_percent'] ?? null) }}</p><p class="text-xs text-gray-500">{{ $marketing['price_lists_booked'] ?? 0 }} of {{ $marketing['price_lists_sent'] ?? 0 }} led to a booking</p></div>
            <div class="{{ $card }}"><p class="{{ $label }}">Enquiries</p><p class="{{ $value }}">{{ $marketing['enquiries'] ?? 0 }}</p></div>
        </div>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <div class="{{ $card }}">
                <p class="{{ $label }}">Booking sources</p>
                <table class="mt-2 w-full text-sm">
                    <caption class="sr-only">Bookings made by source</caption>
                    <thead><tr class="text-left text-gray-500"><th scope="col" class="py-1 font-medium">Source</th><th scope="col" class="py-1 text-right font-medium">Made</th><th scope="col" class="py-1 text-right font-medium">Confirmed</th></tr></thead>
                    <tbody>
                        @forelse (($data['sources'] ?? []) as $source => $row)
                            <tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1">{{ $sourceLabels[$source] ?? $source }}</td><td class="py-1 text-right tabular-nums">{{ $row['made'] }}</td><td class="py-1 text-right tabular-nums">{{ $row['confirmed'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-gray-500">No bookings made in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="{{ $card }}">
                <p class="{{ $label }}">Campaigns (website, first visit)</p>
                <table class="mt-2 w-full text-sm">
                    <caption class="sr-only">Website bookings by campaign</caption>
                    <thead><tr class="text-left text-gray-500"><th scope="col" class="py-1 font-medium">Campaign / site</th><th scope="col" class="py-1 text-right font-medium">Made</th><th scope="col" class="py-1 text-right font-medium">Confirmed</th></tr></thead>
                    <tbody>
                        @forelse (($marketing['campaigns'] ?? []) as $campaign => $row)
                            <tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1">{{ $campaign }}</td><td class="py-1 text-right tabular-nums">{{ $row['made'] }}</td><td class="py-1 text-right tabular-nums">{{ $row['confirmed'] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-gray-500">No tracked campaigns yet. Add utm_source / utm_campaign to links you share.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
