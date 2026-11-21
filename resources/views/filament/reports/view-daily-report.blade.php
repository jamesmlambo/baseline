<x-filament-panels::page>
    @php
        $rand = fn (?int $cents): string => $cents === null ? '—' : \App\Support\Money::cents($cents)->format();
    @endphp

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Snapshot taken {{ $report->generated_at->setTimezone('Africa/Johannesburg')->format('j M Y H:i') }}. These are the figures as they were then; the owner dashboard shows live figures.
    </p>

    @if ($report->isFrozen())
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" aria-labelledby="close-h">
            <h2 id="close-h" class="text-base font-semibold">Day closed {{ $report->frozen_at->setTimezone('Africa/Johannesburg')->format('j M Y H:i') }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Payments received {{ $rand($report->frozen_totals['payments_received_cents'] ?? 0) }}
                · Refunds {{ $rand($report->frozen_totals['refunds_paid_cents'] ?? 0) }}
                · Invoiced {{ $rand($report->frozen_totals['invoiced_cents'] ?? 0) }}
                · Credit notes {{ $rand($report->frozen_totals['credit_notes_cents'] ?? 0) }}
                (transaction date, at closing).
            </p>
        </section>
    @endif

    @include('filament.reports.metrics', ['data' => $report->data])

    @if (isset($report->data['month_to_date']))
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" aria-labelledby="mtd-h">
            <h2 id="mtd-h" class="text-base font-semibold">Month to date</h2>
            <p class="mt-1 text-sm">
                Occupancy {{ $report->data['month_to_date']['rooms']['occupancy_percent'] ?? '—' }} %
                · Room revenue {{ $rand($report->data['month_to_date']['rooms']['room_revenue_cents'] ?? 0) }} (stay date)
                · Payments received {{ $rand($report->data['month_to_date']['money']['payments_received_cents'] ?? 0) }} (transaction date)
                · Arrivals tomorrow {{ $report->data['tomorrow']['arrivals'] ?? 0 }}
            </p>
        </section>
    @endif

    <section aria-labelledby="deliv-h">
        <h2 id="deliv-h" class="text-base font-semibold">Deliveries</h2>
        <table class="mt-2 w-full text-sm">
            <caption class="sr-only">Who the report was sent to, and how</caption>
            <thead><tr class="text-left text-gray-500"><th scope="col" class="py-1 font-medium">To</th><th scope="col" class="py-1 font-medium">By</th><th scope="col" class="py-1 font-medium">Status</th><th scope="col" class="py-1 font-medium">Tries</th><th scope="col" class="py-1 font-medium">Note</th></tr></thead>
            <tbody>
                @forelse ($deliveries as $delivery)
                    <tr class="border-t border-gray-100 dark:border-white/5">
                        <td class="py-1">{{ $delivery->user->name }}</td>
                        <td class="py-1">{{ ucfirst($delivery->channel) }}</td>
                        <td class="py-1 {{ $delivery->status === 'failed' ? 'text-danger-600 font-semibold' : '' }}">{{ ucfirst($delivery->status) }}</td>
                        <td class="py-1">{{ $delivery->attempts }}</td>
                        <td class="py-1 text-gray-500">{{ $delivery->last_error ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-500">Not sent to anyone yet (no active owner account).</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-filament-panels::page>
