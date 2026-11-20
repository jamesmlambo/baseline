<x-mail::message>
# Thank you, {{ $booking->guest?->first_name ?? $booking->lead_guest_name }}

We have received your booking **{{ $booking->reference }}** for {{ $booking->arrival_date->format('l j F Y') }} to {{ $booking->departure_date->format('l j F Y') }}.

**Your room is held until {{ $booking->hold_expires_at?->setTimezone('Africa/Johannesburg')->format('j F Y \a\t H:i') }}.** If payment has not reached us by then, the room is released.

Amount to pay: **{{ $booking->deposit_due_cents->format() }}** (total {{ $booking->total_cents->format() }}). Your invoice is attached.

@if ($bank)
**Pay by EFT** to {{ $bank['bank'] }}, {{ $bank['account_name'] }}, account {{ $bank['account_number'] }}, branch {{ $bank['branch_code'] }}. Use reference **{{ $booking->reference }}**.
@endif

<x-mail::button :url="$statusUrl">
View or pay for your booking
</x-mail::button>

We will never ask for your card details by WhatsApp, SMS or email, and we will never change our banking details by message.

Baseline Lodge
</x-mail::message>
