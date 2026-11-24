<x-mail::message>
# Invoice {{ $invoice->number }}

Hello {{ $invoice->recipient_name }},

@if ($note)
<x-mail::panel>
{!! nl2br(e($note)) !!}
</x-mail::panel>
@endif

@if ($isPaid)
Thank you for your payment. Your invoice for {{ $invoice->total_cents->format() }} is **paid** and attached for your records.
@else
Please find your invoice attached. The amount to pay is **{{ \App\Support\Money::cents(max(0, $balance->cents))->format() }}**@if ($invoice->due_on), due {{ $invoice->due_on->format('j F Y') }}@endif.
@endif

@if ($invoice->booking)
Booking reference: **{{ $invoice->booking->reference }}**
@endif

<x-mail::button :url="$url">
View invoice
</x-mail::button>

Baseline Lodge will never ask for your card details by email, WhatsApp or SMS, and will never change its banking details by message.

Baseline Lodge
</x-mail::message>
