<x-mail::message>
# Your booking is confirmed

Thank you, {{ $booking->guest?->first_name ?? $booking->lead_guest_name }}. Booking **{{ $booking->reference }}** is confirmed for {{ $booking->arrival_date->format('l j F Y') }} to {{ $booking->departure_date->format('l j F Y') }}.

Your receipt is attached.

@if ($statusUrl)
<x-mail::button :url="$statusUrl">
View your booking
</x-mail::button>
@endif

We look forward to welcoming you.

Baseline Lodge
</x-mail::message>
