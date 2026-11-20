<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DTOs\CheckoutSessionResult;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PayMongoGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        $secretKey = config('services.paymongo.secret_key');

        return is_string($secretKey) && trim($secretKey) !== '';
    }

    public function createCheckoutSession(
        Order $order,
        Payment $payment,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult {
        $secretKey = $this->requireSecretKey();
        $baseUrl = rtrim((string) config('services.paymongo.base_url', 'https://api.paymongo.com/v1'), '/');

        $order->loadMissing('items');

        $lineItems = $order->items->map(static fn (OrderItem $item): array => [
            'currency' => strtoupper($item->currency),
            'amount' => MoneyFormatter::toCentavos($item->unit_price),
            'name' => $item->variant_title !== null && $item->variant_title !== ''
                ? $item->product_name.' — '.$item->variant_title
                : $item->product_name,
            'quantity' => $item->quantity,
        ])->values()->all();

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(12)
            ->retry(1, 250, throw: false)
            ->post($baseUrl.'/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => 'XHopy Order '.$order->order_number,
                        'reference_number' => $order->order_number,
                        'line_items' => $lineItems,
                        'payment_method_types' => ['card', 'gcash', 'paymaya', 'qrph'],
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'order_number' => $order->order_number,
                            'payment_id' => (string) $payment->id,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to initialize PayMongo checkout session.');
        }

        $sessionId = $response->json('data.id');
        $checkoutUrl = $response->json('data.attributes.checkout_url');

        if (! is_string($sessionId) || $sessionId === '' || ! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('PayMongo response omitted required checkout session attributes.');
        }

        return new CheckoutSessionResult(
            providerReference: $sessionId,
            checkoutUrl: $checkoutUrl,
            isSandboxSimulation: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $secretKey = $this->requireSecretKey();
        $baseUrl = rtrim((string) config('services.paymongo.base_url', 'https://api.paymongo.com/v1'), '/');

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->timeout(10)
            ->get($baseUrl.'/checkout_sessions/'.urlencode($sessionId));

        if (! $response->successful()) {
            throw new RuntimeException('Failed retrieving PayMongo checkout session status.');
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->json('data', []);

        return $data;
    }

    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool
    {
        $webhookSecret = config('services.paymongo.webhook_secret');
        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            return false;
        }

        $parsed = self::parseSignatureHeader($signatureHeader);
        $timestamp = $parsed['t'] ?? '';

        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = (int) config('services.paymongo.webhook_tolerance_seconds', 300);
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $mode = mb_strtolower((string) config('services.paymongo.mode', 'test'));
        $expectedSignature = $mode === 'live' ? ($parsed['li'] ?? '') : ($parsed['te'] ?? '');

        if ($expectedSignature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $webhookSecret);

        return hash_equals($expectedSignature, $computed);
    }

    public static function signPayload(string $rawPayload, string $webhookSecret, int $timestamp, string $mode = 'test'): string
    {
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $webhookSecret);

        return $mode === 'live'
            ? "t={$timestamp},te=,li={$signature}"
            : "t={$timestamp},te={$signature},li=";
    }

    /**
     * @return array<string, string>
     */
    private static function parseSignatureHeader(string $header): array
    {
        $result = [];

        foreach (explode(',', $header) as $segment) {
            $trimmed = trim($segment);
            if ($trimmed === '' || ! str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $result[trim($key)] = trim($value);
        }

        return $result;
    }

    private function requireSecretKey(): string
    {
        $secretKey = config('services.paymongo.secret_key');

        if (! is_string($secretKey) || trim($secretKey) === '') {
            throw new RuntimeException('PayMongo secret key is not configured.');
        }

        return trim($secretKey);
    }
}
