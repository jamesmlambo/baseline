<x-pdf-layout :title="$title" :document="$document" :contact="$contact" :vat-number="$vatNumber" :is-demo="$isDemo" :reception-qr="$receptionQr">
    <table class="meta">
        <tr>
            <td>
                <div class="muted">For</div>
                <strong>{{ $document->recipient_name }}</strong>
            </td>
            <td style="text-align:right;">
                @if ($document->booking)
                    <div class="muted">Booking reference</div>
                    <strong>{{ $document->booking->reference }}</strong>
                @endif
                @if ($document instanceof \App\Models\Invoice && $document->due_on)
                    <div class="muted" style="margin-top:6px;">Due {{ $document->due_on->format('j F Y') }}</div>
                @endif
                @if ($document instanceof \App\Models\Quotation && $document->valid_until)
                    <div class="muted" style="margin-top:6px;">Valid until {{ $document->valid_until->format('j F Y') }}</div>
                @endif
                @if ($document instanceof \App\Models\CreditNote && $document->invoice)
                    <div class="muted" style="margin-top:6px;">Against invoice {{ $document->invoice->number }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($document instanceof \App\Models\CreditNote)
        <div class="box"><strong>Reason:</strong> {{ $document->reason }}</div>
        <table class="totals">
            <tr class="grand"><td>Credit</td><td class="num">{{ $document->total_cents->format() }}</td></tr>
        </table>
    @else
        <table class="lines">
            <thead>
                <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="num">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                        <td class="num">{{ $line->unit_price_cents->format() }}</td>
                        <td class="num">{{ $line->total_cents->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Subtotal</td><td class="num">{{ $document->subtotal_cents->format() }}</td></tr>
            @if (! $document->tax_cents->isZero())
                <tr><td>VAT</td><td class="num">{{ $document->tax_cents->format() }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="num">{{ $document->total_cents->format() }}</td></tr>
            @if ($document instanceof \App\Models\Invoice)
                <tr><td>Paid</td><td class="num">{{ $document->amountPaid()->format() }}</td></tr>
                @if (! $document->creditedAmount()->isZero())
                    <tr><td>Credited</td><td class="num">{{ $document->creditedAmount()->format() }}</td></tr>
                @endif
                <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ \App\Support\Money::cents(max(0, $document->balanceDue()->cents))->format() }}</strong></td></tr>
            @endif
        </table>

        @if ($bank)
            <div class="box">
                <strong>Pay by EFT</strong><br>
                {{ $bank['bank'] }} · {{ $bank['account_name'] }}<br>
                Account {{ $bank['account_number'] }} · Branch {{ $bank['branch_code'] }}<br>
                Reference: <strong>{{ $document->booking?->reference ?? $document->number }}</strong><br>
                <span class="muted">Your booking is confirmed once the money reaches our account. Proof of payment alone does not confirm a booking.</span>
            </div>
        @endif
    @endif
</x-pdf-layout>
