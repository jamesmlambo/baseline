<x-layouts.portal title="Your bill" :booking="$booking">
    <h1 class="font-display text-2xl font-semibold text-lodge-900">Your bill</h1>

    <div class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-sand-200">
        <table class="w-full text-sm">
            <caption class="sr-only">Charges and payments</caption>
            <tbody class="divide-y divide-sand-200">
                @foreach ($invoices as $invoice)
                    @foreach ($invoice->lines as $line)
                        <tr>
                            <th scope="row" class="p-3 text-left font-normal">{{ $line->description }}@if ((float) $line->quantity !== 1.0) × {{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}@endif</th>
                            <td class="p-3 text-right whitespace-nowrap">{{ $line->total_cents->format() }}</td>
                        </tr>
                    @endforeach
                    @if (! $invoice->tax_cents->isZero())
                        <tr><th scope="row" class="p-3 text-left font-normal">VAT ({{ $invoice->number }})</th><td class="p-3 text-right">{{ $invoice->tax_cents->format() }}</td></tr>
                    @endif
                @endforeach
                @foreach ($payments as $payment)
                    <tr class="text-emerald-800">
                        <th scope="row" class="p-3 text-left font-normal">Paid — {{ $payment->method->label() }}</th>
                        <td class="p-3 text-right whitespace-nowrap">−{{ $payment->amount_cents->subtract($payment->refunded_cents)->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink-900/20">
                    <th scope="row" class="p-3 text-left font-semibold">Still to pay</th>
                    <td class="p-3 text-right text-lg font-semibold">{{ \App\Support\Money::cents(max(0, $balance->cents))->format() }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($balance->cents > 0)
        <a href="{{ route('booking.show', $booking->public_token) }}" class="mt-4 block w-full rounded-full bg-lodge-800 px-6 py-3 text-center font-semibold text-white hover:bg-lodge-700">Pay now</a>
    @endif
    @if ($awaitingVerification)
        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm ring-1 ring-amber-200">A payment is still being checked. Reception will confirm it.</p>
    @endif

    <section class="mt-8 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-sand-200">
        <h2 class="font-display text-lg font-semibold text-lodge-900">Check out</h2>
        <p class="mt-1 text-sm text-ink-900/70">
            @if ($balance->cents <= 0 && ! $awaitingVerification)
                Nothing is owed, so you can check out from here. Please leave your key at reception or in the room.
            @else
                Once your bill is settled you can check out here, or reception can help you.
            @endif
        </p>
        <form method="POST" action="{{ route('portal.checkout') }}" class="mt-4" onsubmit="return confirm('Check out now? You will be signed out of the portal.');">
            @csrf
            <button class="w-full rounded-full border border-lodge-800 px-6 py-3 font-semibold text-lodge-900 hover:bg-lodge-800/5">Check out</button>
        </form>
    </section>
</x-layouts.portal>
