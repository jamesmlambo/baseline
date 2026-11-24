<x-pdf-layout :title="$title" :document="$document" :contact="$contact" :vat-number="$vatNumber" :is-demo="$isDemo" :reception-qr="$receptionQr">
    <table class="meta">
        <tr>
            <td>
                <div class="muted">For</div>
                <strong>{{ $document->recipient_name }}</strong>
            </td>
            <td style="text-align:right;">
                @if ($stamp ?? null)
                    @php($stampColour = ['PAID' => '#15803d', 'UNPAID' => '#b91c1c'][$stamp] ?? '#57534e')
                    <div style="display:inline-block;margin-bottom:8px;padding:4px 14px;border:3px solid {{ $stampColour }};color:{{ $stampColour }};font-size:20px;font-weight:bold;letter-spacing:2px;">{{ $stamp }}</div>
                @endif
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

        @if ($document instanceof \App\Models\Quotation)
            @if ($document->notes)
                <div class="box">{!! nl2br(e($document->notes)) !!}</div>
            @endif
            <p class="muted" style="margin-top:14px;">This is a quotation, not a booking: rooms and services are only reserved once you book and pay. Prices are valid until the date shown above, subject to availability.</p>
        @endif

        @if ($invoiceQr ?? null)
            <table style="width:100%;margin-top:18px;">
                <tr>
                    <td style="width:90px;"><img src="{{ $invoiceQr }}" width="80" height="80" alt="QR code for {{ strtolower($title) }} {{ $document->number }}"></td>
                    <td class="muted" style="vertical-align:middle;font-size:9px;">
                        Scan to open this {{ strtolower($title) }} online and check that it is genuine and up to date.
                    </td>
                </tr>
            </table>
        @endif

        @if ($bank && ($stamp ?? null) !== 'PAID')
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
