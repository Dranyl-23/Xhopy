<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\Actions\ProcessPayMongoWebhookAction;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ProcessPayMongoWebhookAction $processWebhook,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signatureHeader = (string) $request->header('Paymongo-Signature', '');

        if (! $this->gateway->verifyWebhookSignature($rawPayload, $signatureHeader)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $decoded = json_decode($rawPayload, true);

        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'Malformed webhook payload.',
            ], 400);
        }

        $outcome = $this->processWebhook->execute($decoded);

        return response()->json([
            'received' => true,
            'status' => $outcome,
        ]);
    }
}
