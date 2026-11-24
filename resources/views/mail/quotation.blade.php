<x-mail::message>
# Quotation {{ $quotation->number }}

Hello {{ $quotation->recipient_name }},

@if ($note)
<x-mail::panel>
{!! nl2br(e($note)) !!}
</x-mail::panel>
@endif

Thank you for your interest in Baseline Lodge. Your quotation for **{{ $quotation->total_cents->format() }}** is attached{{ $quotation->valid_until ? ', valid until '.$quotation->valid_until->format('j F Y') : '' }}.

This is a quotation, not a booking: rooms and services are only reserved once you book and pay.

@if ($url)
<x-mail::button :url="$url">
View quotation
</x-mail::button>
@endif

Baseline Lodge will never ask for your card details by email, WhatsApp or SMS, and will never change its banking details by message.

Baseline Lodge
</x-mail::message>
