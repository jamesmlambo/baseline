<x-mail::message>
# Daily report — {{ $date->format('l j F Y') }}

@php
    $rand = fn (?int $cents): string => $cents === null ? '—' : \App\Support\Money::cents($cents)->format();
    $rooms = $data['rooms'] ?? [];
    $money = $data['money'] ?? [];
    $ops = $data['operations'] ?? [];
@endphp

| Today | |
|:--|--:|
| Occupancy (stay date) | {{ isset($rooms['occupancy_percent']) ? $rooms['occupancy_percent'].' %' : '—' }} |
| Rooms sold / available | {{ $rooms['nights_sold'] ?? 0 }} / {{ $rooms['rooms_available'] ?? 0 }} |
| Room revenue (stay date) | {{ $rand($rooms['room_revenue_cents'] ?? 0) }} |
| Payments received (transaction date) | {{ $rand($money['payments_received_cents'] ?? 0) }} |
| Arrivals / departures | {{ $ops['arrivals'] ?? 0 }} / {{ $ops['departures'] ?? 0 }} |
| Open guest requests | {{ ($ops['guest_requests_open'] ?? 0) + ($ops['guest_messages_unanswered'] ?? 0) }} |
| Open complaints | {{ $ops['complaints_open'] ?? 0 }} |

<x-mail::button :url="$url">
Open the full report
</x-mail::button>

You will be asked to sign in. This email contains no guest details.
</x-mail::message>
