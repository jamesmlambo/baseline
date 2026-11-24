<?php

declare(strict_types=1);

require_once __DIR__.'/FinanceTestHelpers.php';

use App\Actions\Payments\IssueBookingInvoice;
use App\Enums\BookingStatus;
use App\Enums\MessageChannel;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Mail\BookingConfirmedMail;
use App\Mail\InvoiceMail;
use App\Models\DocumentDelivery;
use App\Models\Invoice;
use App\Models\Message;
use App\Services\Finance\DocumentPdf;
use App\Services\Finance\InvoiceDelivery;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\PaymentLedger;
use Database\Seeders\MessageTemplateSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    seedFinanceSettings();
    $this->seed(MessageTemplateSeeder::class);
    Mail::fake();
    $this->travelTo(now('Africa/Johannesburg')->setDate(2027, 6, 1)->setTime(9, 0));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('PAY-02 emails the invoice stamped PAID and sends an SMS when an online payment succeeds', function (): void {
    $booking = reserve();
    $invoice = $booking->currentInvoice();

    expect($invoice->statusLabel())->toBe('UNPAID');

    sendFakeWebhook(GatewayEvent::SUCCEEDED, startFakePayment($booking))->assertOk();

    expect($invoice->fresh()->statusLabel())->toBe('PAID');

    Mail::assertSent(BookingConfirmedMail::class, function (BookingConfirmedMail $mail) use ($invoice): bool {
        $names = collect($mail->attachments())->map(fn ($attachment) => $attachment->as)->all();

        return $mail->hasTo('guest@example.test') && in_array($invoice->number.'.pdf', $names, true);
    });

    $sms = Message::query()->where('idempotency_key', 'payment_confirmed:'.$booking->id)->sole();
    expect($sms->channel)->toBe(MessageChannel::Sms);
});

it('PAY-02 prints a QR code to the invoice on every invoice, with PAID in green or UNPAID in red', function (): void {
    $booking = reserve();
    $invoice = $booking->currentInvoice();
    $pdf = app(DocumentPdf::class);

    $unpaid = $pdf->html($invoice);
    expect($unpaid)->toContain('#b91c1c;font-size:20px;font-weight:bold;letter-spacing:2px;">UNPAID')
        ->toContain('alt="QR code for invoice '.$invoice->number.'"');

    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->total_cents, staffUser(StaffRole::Receptionist));

    expect($pdf->html($invoice->fresh()))->toContain('#15803d;font-size:20px;font-weight:bold;letter-spacing:2px;">PAID')
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('PAY-02 lists and searches invoices for reception, and keeps housekeeping out', function (): void {
    $first = reserve()->currentInvoice();
    $second = reserve(arrival: '2027-07-01', departure: '2027-07-03')->currentInvoice();

    $this->actingAs(staffUser(StaffRole::Receptionist));
    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$first, $second])
        ->searchTable($second->number)
        ->assertCanSeeTableRecords([$second])
        ->assertCanNotSeeTableRecords([$first])
        ->assertTableColumnStateSet('status', 'UNPAID', $second);

    expect(staffUser(StaffRole::HousekeepingWorker)->can('viewAny', Invoice::class))->toBeFalse();
});

it('PAY-02 makes an UNPAID invoice for an extra charge against a booking and emails it to the guest', function (): void {
    $booking = reserve();
    app(PaymentLedger::class)->recordAtReception($booking, PaymentMethod::Cash, $booking->total_cents, staffUser(StaffRole::Receptionist));
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'booking_id' => $booking->id,
            'kind' => IssueBookingInvoice::CHARGE,
            'description' => 'Airport transfer',
            'quantity' => 1,
            'unit_price' => '350',
            'send_email' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    [$stayInvoice, $invoice] = $booking->invoices()->get()->all();

    expect($invoice->total_cents->cents)->toBe(35_000)
        ->and($invoice->statusLabel())->toBe('UNPAID')
        ->and($stayInvoice->statusLabel())->toBe('PAID')
        ->and($booking->fresh()->balanceDue()->cents)->toBe(35_000);

    Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->hasTo('guest@example.test') && $mail->invoice->is($invoice));
});

it('PAY-02 refuses a second whole-stay invoice for a booking that already has one', function (): void {
    $booking = reserve();
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(CreateInvoice::class)
        ->fillForm(['booking_id' => $booking->id, 'kind' => IssueBookingInvoice::STAY])
        ->call('create')
        ->assertNotified('Invoice not created');

    expect($booking->invoices()->count())->toBe(1);
});

it('PAY-02 emails an invoice to any address and opens WhatsApp with the invoice link', function (): void {
    $booking = reserve();
    $invoice = $booking->currentInvoice();
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('email', data: ['email' => 'accounts@example.test', 'message' => "Thanks for staying.\nThe transfer is included."])
        ->assertHasNoActionErrors();

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail): bool {
        $mail->assertSeeInOrderInHtml(['Hello', 'Thanks for staying.', 'The transfer is included.', 'View invoice']);

        return $mail->hasTo('accounts@example.test');
    });

    $url = app(InvoiceDelivery::class)->whatsAppUrl($invoice, $booking->lead_guest_phone);

    expect($url)->toStartWith('https://wa.me/27825550101?text=')
        ->and(rawurldecode($url))->toContain($invoice->publicUrl())->toContain('Amount to pay: R');
});

it('PAY-02 shows the message typed by staff as plain text in the invoice email', function (): void {
    $invoice = reserve()->currentInvoice();

    $html = (new InvoiceMail($invoice, '<script>alert(1)</script> See you soon'))->render();

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt; See you soon')
        ->not->toContain('<script>alert(1)</script>')
        ->and((new InvoiceMail($invoice))->render())->not->toContain('class="panel"');
});

it('PAY-02 downloads the invoice PDF from the Invoices section', function (): void {
    $invoice = reserve()->currentInvoice();
    $this->actingAs(staffUser(StaffRole::Receptionist));

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('download')
        ->assertFileDownloaded($invoice->number.'.pdf');
});

it('PAY-02 records the invoice and receipt as emailed when the booking confirmation goes out', function (): void {
    $booking = reserve();
    sendFakeWebhook(GatewayEvent::SUCCEEDED, startFakePayment($booking))->assertOk();

    $invoice = $booking->currentInvoice();
    $receipt = $booking->receipts()->sole();

    expect($invoice->emailStatus())->toBe('Sent 1× to guest@example.test')
        ->and($invoice->deliveries()->sole()->reason)->toBe(DocumentDelivery::REASON_BOOKING_CONFIRMED)
        ->and($receipt->emailStatus())->toBe('Sent 1× to guest@example.test');
});

it('PAY-02 shows where an invoice was emailed and how many times to each address', function (): void {
    $invoice = reserve()->currentInvoice();
    $this->actingAs($receptionist = staffUser(StaffRole::Receptionist));

    Livewire::test(ListInvoices::class)->assertTableColumnStateSet('email_status', 'Not sent', $invoice);

    foreach (['guest@example.test', 'Guest@Example.test'] as $address) {
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('email', data: ['email' => $address])
            ->assertHasNoActionErrors();
    }

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('email', data: ['email' => 'accounts@example.test'])
        ->assertSee('Sent 3× to 2 addresses')
        ->assertSee('accounts@example.test');

    $summary = $invoice->fresh()->emailSummary()->mapWithKeys(fn (array $row): array => [$row['email'] => $row['times']])->all();

    expect($summary)->toBe(['accounts@example.test' => 1, 'guest@example.test' => 2])
        ->and($invoice->deliveries()->first()->sent_by)->toBe($receptionist->id)
        ->and(DB::table('document_deliveries')->pluck('recipient')->implode(' '))->not->toContain('example.test');

    Livewire::test(ListInvoices::class)->assertTableColumnStateSet('email_status', 'Sent 3× to 2 addresses', $invoice);
});
