@php
    $sections = [
        'Arriving today — '.today()->format('l j F Y') => $arrivalsToday,
        'Arriving tomorrow — '.today()->addDay()->format('l j F Y') => $arrivalsTomorrow,
        'In house' => $inHouse,
    ];
    $cell = 'padding:.4rem .5rem;border-bottom:1px solid #d6d3d1;text-align:left;vertical-align:top;';
@endphp
<x-filament-panels::page>
    <style>
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .no-print { display: none !important; }
            .fi-main { padding: 0 !important; max-width: none !important; }
            body { background: #fff !important; }
        }
    </style>

    <div class="no-print" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
        <x-filament::button icon="heroicon-o-printer" x-on:click="window.print()">Print</x-filament::button>
        <span style="font-size:.875rem;opacity:.75;">Print this at the start of each shift. If the system is offline, use it to greet arrivals — but do not take new bookings or payments until it is back.</span>
    </div>

    <div style="background:#fff;color:#1c1917;border-radius:.75rem;padding:1.25rem;font-size:.8rem;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:2px solid #0f514b;padding-bottom:.5rem;margin-bottom:1rem;">
            <strong style="font-size:1.1rem;color:#0f514b;">Baseline Lodge — reception sheet</strong>
            <span>Printed {{ $generatedAt->format('j M Y H:i') }} · valid for today only · CONFIDENTIAL</span>
        </div>

        @foreach ($sections as $heading => $bookings)
            <h2 style="font-size:.95rem;font-weight:700;margin:1rem 0 .4rem;">{{ $heading }} ({{ $bookings->count() }})</h2>
            @if ($bookings->isEmpty())
                <p style="opacity:.7;">None.</p>
            @else
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th scope="col" style="{{ $cell }}">Room</th>
                            <th scope="col" style="{{ $cell }}">Guest</th>
                            <th scope="col" style="{{ $cell }}">Reference</th>
                            <th scope="col" style="{{ $cell }}">Stay</th>
                            <th scope="col" style="{{ $cell }}">Status</th>
                            <th scope="col" style="{{ $cell }}text-align:right;">Owing</th>
                            <th scope="col" style="{{ $cell }}">Requests</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td style="{{ $cell }}font-weight:600;">{{ $booking->activeBookingRooms->map(fn ($room) => $room->room->name)->implode(', ') }}</td>
                                <td style="{{ $cell }}">{{ $booking->lead_guest_name }}</td>
                                <td style="{{ $cell }}font-family:monospace;">{{ $booking->reference }}</td>
                                <td style="{{ $cell }}">{{ $booking->arrival_date->format('j M') }} – {{ $booking->departure_date->format('j M') }} ({{ $booking->nights() }})</td>
                                <td style="{{ $cell }}">{{ $booking->status->label() }}</td>
                                <td style="{{ $cell }}text-align:right;">{{ ($balance = $booking->balanceDue())->cents > 0 ? $balance->format() : '—' }}</td>
                                <td style="{{ $cell }}">{{ \Illuminate\Support\Str::limit((string) $booking->special_requests, 60) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        <p style="margin-top:1rem;font-size:.7rem;opacity:.7;">Contains personal information. Keep at the desk and shred at the end of the day. Holds marked "Hold" are not confirmed.</p>
    </div>
</x-filament-panels::page>
