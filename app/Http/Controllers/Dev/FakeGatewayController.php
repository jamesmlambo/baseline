<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\GatewayEvent;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Local stand-in for a provider's hosted payment page. Never registered in production.
 * "Approve" and "Decline" send a properly signed webhook through the real webhook pipeline.
 */
class FakeGatewayController extends Controller
{
    public function show(Request $request): View
    {
        $payment = Payment::query()->where('gateway', 'fake')->where('gateway_reference', $request->query('reference'))->firstOrFail();

        return view('dev.fake-gateway', [
            'payment' => $payment,
            'action' => URL::temporarySignedRoute('dev.fake-gateway.complete', now()->addHour(), [
                'reference' => $request->query('reference'),
                'return' => $request->query('return'),
                'cancel' => $request->query('cancel'),
            ]),
        ]);
    }

    public function complete(Request $request, FakeGateway $gateway, WebhookProcessor $processor): RedirectResponse
    {
        $payment = Payment::query()->where('gateway', 'fake')->where('gateway_reference', $request->query('reference'))->firstOrFail();

        $type = match ($request->input('outcome')) {
            'approve' => GatewayEvent::SUCCEEDED,
            'decline' => GatewayEvent::FAILED,
            default => GatewayEvent::CANCELLED,
        };

        $webhook = $gateway->makeWebhook($type, $payment->gateway_reference, $payment->amount_cents->cents);
        $webhookRequest = Request::create('/webhooks/payments/fake', 'POST', server: [
            'HTTP_'.str_replace('-', '_', strtoupper(FakeGateway::SIGNATURE_HEADER)) => $webhook['signature'],
            'CONTENT_TYPE' => 'application/json',
        ], content: $webhook['body']);

        $processor->process($gateway, $webhookRequest);

        $back = $type === GatewayEvent::SUCCEEDED ? $request->query('return') : $request->query('cancel');

        return redirect()->to((string) $back)->with('status', match ($type) {
            GatewayEvent::SUCCEEDED => 'Payment received — thank you!',
            GatewayEvent::FAILED => 'The payment was declined. You can try again or choose another method.',
            default => 'Payment cancelled.',
        });
    }
}
