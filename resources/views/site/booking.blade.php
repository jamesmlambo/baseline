@php
    use App\Enums\BookingStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentRecordStatus;

    $status = $booking->status;
    $holdExpired = $status === BookingStatus::Hold && $booking->hold_expires_at?->isPast();
    $awaitingEft = $booking->payments->contains(fn ($payment) => $payment->status === PaymentRecordStatus::PendingVerification);
    $paidSoFar = $booking->paidTotal();
    $canPay = in_array($status, [BookingStatus::Hold, BookingStatus::Pending], true) && ! $holdExpired;
    $preferred = $booking->preferred_payment_method;
@endphp
<x-layouts.site :title="'Booking '.$booking->reference" :noindex="true">
    <x-site.page-header :title="'Booking '.$booking->reference" :summary="$booking->arrival_date->format('D j M Y').' – '.$booking->departure_date->format('D j M Y')" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[3fr_2fr]">
        <section class="space-y-6" aria-labelledby="status-heading">
            <h2 id="status-heading" class="sr-only">Booking status</h2>

            @if ($status === BookingStatus::Confirmed || $status === BookingStatus::CheckedIn || $status === BookingStatus::CheckedOut)
                <div class="rounded-2xl bg-lodge-800/5 p-6 ring-1 ring-lodge-700/20" role="status">
                    <p class="font-display text-2xl font-semibold text-lodge-900">Your booking is confirmed</p>
                    <p class="mt-2 text-ink-900/80">We look forward to welcoming you. A confirmation has been emailed to you.</p>
                </div>
            @elseif ($holdExpired || $status === BookingStatus::Expired)
                <div class="rounded-2xl bg-red-50 p-6 text-red-900 ring-1 ring-red-200" role="alert">
                    <p class="font-display text-2xl font-semibold">Your room hold has expired</p>
                    <p class="mt-2">We did not receive payment in time, so the room was released. If you have already paid, please <a class="font-semibold underline" href="{{ route('contact') }}">contact us</a> with reference {{ $booking->reference }} and we will sort it out.</p>
                </div>
            @elseif ($status === BookingStatus::Cancelled)
                <div class="rounded-2xl bg-sand-100 p-6" role="status">
                    <p class="font-display text-2xl font-semibold text-ink-900">This booking was cancelled</p>
                </div>
            @elseif ($awaitingEft)
                <div class="rounded-2xl bg-amber-50 p-6 ring-1 ring-amber-200" role="status">
                    <p class="font-display text-2xl font-semibold text-ink-900">We're checking your payment</p>
                    <p class="mt-2 text-ink-900/80">Your booking is confirmed as soon as the EFT reaches our bank account. You don't need to do anything else.</p>
                    @if ($booking->hold_expires_at)
                        <p class="mt-2 text-sm text-ink-900/70">Room held until {{ $booking->hold_expires_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i') }}.</p>
                    @endif
                </div>
            @elseif ($canPay)
                <div class="rounded-2xl bg-amber-50 p-6 ring-1 ring-amber-200" role="status">
                    <p class="font-display text-2xl font-semibold text-ink-900">Your room is held — please pay to confirm</p>
                    <p class="mt-2 text-ink-900/80">
                        Pay <strong>{{ \App\Support\Money::cents(max(0, $booking->deposit_due_cents->cents - $paidSoFar->cents))->format() }}</strong>
                        @if ($booking->hold_expires_at)
                            by <strong>{{ $booking->hold_expires_at->setTimezone('Africa/Johannesburg')->format('D j M, H:i') }}</strong>
                        @endif
                        or the room is released.
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach ($methods as $method)
                        @if ($method->isOnline())
                            <form method="POST" action="{{ route('booking.pay', $booking->public_token) }}">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method->value }}">
                                <button type="submit" @class([
                                    'w-full rounded-full px-6 py-3.5 font-semibold transition',
                                    'bg-lodge-800 text-white hover:bg-lodge-700' => $method === ($preferred?->isOnline() ? $preferred : PaymentMethod::InstantEft),
                                    'border border-sand-300 bg-white text-ink-900 hover:bg-sand-100' => $method !== ($preferred?->isOnline() ? $preferred : PaymentMethod::InstantEft),
                                ])>
                                    Pay with {{ $method->label() }}
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>

                @if ($bank)
                    <details class="rounded-2xl border border-sand-200 bg-white p-5" @if ($preferred === PaymentMethod::Eft) open @endif>
                        <summary class="cursor-pointer font-semibold text-ink-900">Pay by EFT (bank transfer)</summary>
                        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt class="text-ink-900/60">Bank</dt><dd>{{ $bank['bank'] }}</dd>
                            <dt class="text-ink-900/60">Account name</dt><dd>{{ $bank['account_name'] }}</dd>
                            <dt class="text-ink-900/60">Account number</dt><dd class="font-mono">{{ $bank['account_number'] }}</dd>
                            <dt class="text-ink-900/60">Branch code</dt><dd class="font-mono">{{ $bank['branch_code'] }}</dd>
                            <dt class="text-ink-900/60">Reference</dt><dd class="font-mono font-semibold">{{ $booking->reference }}</dd>
                        </dl>
                        <p class="mt-3 text-xs text-ink-900/60">Your booking is confirmed when the money reaches our account. A proof of payment helps us find it faster but does not confirm the booking on its own.</p>
                        <form method="POST" action="{{ route('booking.eft', $booking->public_token) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            <label class="block text-sm font-semibold" for="proof">Proof of payment (optional, PDF or photo)</label>
                            <input id="proof" type="file" name="proof" accept=".pdf,image/jpeg,image/png" class="block w-full text-sm">
                            @error('proof')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            <button type="submit" class="rounded-full bg-ink-900 px-5 py-2.5 text-sm font-semibold text-white">I have paid by EFT</button>
                        </form>
                    </details>
                @endif
            @endif

            <div class="rounded-2xl bg-amber-50 p-4 text-sm text-ink-900/80 ring-1 ring-amber-200">
                We will never ask for your card details by WhatsApp, SMS or email, and we never change our banking details by message.
            </div>
        </section>

        <aside class="h-fit rounded-2xl border border-sand-200 bg-white p-6 shadow-sm" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="font-display text-xl font-semibold text-lodge-900">Summary</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-900/60">Guest</dt><dd>{{ $booking->lead_guest_name }}</dd></div>
                @foreach ($booking->activeBookingRooms as $room)
                    <div class="flex justify-between gap-4"><dt class="text-ink-900/60">{{ $room->accommodationType->name }}</dt><dd>{{ $room->accommodation_total_cents->format() }}</dd></div>
                @endforeach
                @foreach ($booking->extras as $extra)
                    <div class="flex justify-between gap-4"><dt class="text-ink-900/60">{{ $extra->description }} × {{ $extra->quantity }}</dt><dd>{{ $extra->total_cents->format() }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-4 border-t border-sand-200 pt-2 font-semibold"><dt>Total</dt><dd>{{ $booking->total_cents->format() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-900/60">Paid</dt><dd>{{ $paidSoFar->format() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-900/60">Payment status</dt><dd>{{ $booking->payment_status->label() }}</dd></div>
            </dl>
            <ul class="mt-5 space-y-2 text-sm">
                @if ($invoice)
                    <li><a class="font-medium text-lodge-700 underline" href="{{ route('documents.show', ['invoice', $invoice->public_token]) }}" target="_blank">Invoice {{ $invoice->number }} (PDF)</a></li>
                @endif
                @foreach ($booking->receipts as $receipt)
                    <li><a class="font-medium text-lodge-700 underline" href="{{ route('documents.show', ['receipt', $receipt->public_token]) }}" target="_blank">Receipt {{ $receipt->number }} (PDF)</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-layouts.site>
