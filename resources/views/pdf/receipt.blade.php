<x-pdf-layout :title="$title" :document="$document" :contact="$contact" :vat-number="$vatNumber" :is-demo="$isDemo">
    <table class="meta">
        <tr>
            <td>
                <div class="muted">Received from</div>
                <strong>{{ $document->recipient_name }}</strong>
            </td>
            <td style="text-align:right;">
                @if ($document->booking)
                    <div class="muted">Booking reference</div>
                    <strong>{{ $document->booking->reference }}</strong>
                @endif
                @if ($document->invoice)
                    <div class="muted" style="margin-top:6px;">For invoice {{ $document->invoice->number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="totals" style="margin-top:28px;">
        <tr><td>Payment method</td><td class="num">{{ $document->payment->method->label() }}</td></tr>
        <tr><td>Date received</td><td class="num">{{ ($document->payment->received_at ?? $document->issued_at)->setTimezone('Africa/Johannesburg')->format('j M Y H:i') }}</td></tr>
        <tr class="grand"><td>Amount received</td><td class="num">{{ $document->amount_cents->format() }}</td></tr>
    </table>

    <div class="box">Thank you. This receipt confirms money received by Baseline Lodge.</div>
</x-pdf-layout>
