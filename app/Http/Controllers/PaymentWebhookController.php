<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Receives payment-provider webhooks (PAY-06). Unsigned or tampered calls get 401;
 * duplicates are acknowledged with 200 so the provider stops retrying.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways, WebhookProcessor $processor): JsonResponse
    {
        try {
            $driver = $gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $outcome = $processor->process($driver, $request);
        } catch (InvalidWebhookSignature) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return response()->json(['outcome' => $outcome]);
    }
}
