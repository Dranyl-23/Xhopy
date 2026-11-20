<?php

namespace App\Http\Controllers\Checkout;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Orders\Actions\PlaceOrderAction;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Actions\CreateCheckoutPaymentAction;
use App\Domain\Payments\Actions\ProcessPayMongoWebhookAction;
use App\Domain\Payments\Actions\ReconcileOrderPaymentAction;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $cart = $this->cartService->findExistingCart($user, null);
        $presentedCart = $this->cartService->presentCart($cart);

        if ($presentedCart['itemCount'] === 0) {
            return redirect()->route('cart.index');
        }

        $addresses = $user->addresses()
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('updated_at')
            ->get()
            ->map(static fn (CustomerAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'is_default_shipping' => $address->is_default_shipping,
                'formatted_line' => $address->formattedLine(),
            ])
            ->values()
            ->all();

        $defaultAddressId = collect($addresses)->firstWhere('is_default_shipping', true)['id']
            ?? ($addresses[0]['id'] ?? null);

        return Inertia::render('checkout/Index', [
            'cart' => $presentedCart,
            'cartSummary' => [
                'itemCount' => $presentedCart['itemCount'],
                'subtotalFormatted' => $presentedCart['subtotalFormatted'],
                'items' => array_slice($presentedCart['items'], 0, 5),
            ],
            'addresses' => $addresses,
            'defaultAddressId' => $defaultAddressId,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        PlaceOrderRequest $request,
        PlaceOrderAction $placeOrder,
        CreateCheckoutPaymentAction $createPayment,
    ): SymfonyResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $address = CustomerAddress::query()->findOrFail((int) $request->validated('address_id'));
        Gate::authorize('view', $address);

        $order = $placeOrder->execute(
            user: $user,
            address: $address,
            idempotencyKey: (string) $request->validated('idempotency_key'),
            notes: is_string($request->validated('notes')) ? (string) $request->validated('notes') : null,
        );

        $payment = $createPayment->execute($order);
        $checkoutUrl = (string) $payment->checkout_url;

        if ($checkoutUrl !== '' && str_starts_with($checkoutUrl, 'https://')) {
            return Inertia::location($checkoutUrl);
        }

        return redirect()->to($checkoutUrl !== '' ? $checkoutUrl : route('orders.show', ['order' => $order->id]));
    }

    public function success(
        Request $request,
        Order $order,
        ReconcileOrderPaymentAction $reconcile,
    ): Response {
        Gate::authorize('view', $order);

        $reconciled = $reconcile->execute($order);
        $reconciled->load(['items', 'latestPayment']);

        return Inertia::render('checkout/Result', [
            'outcome' => 'success',
            'order' => $this->presentOrderSummary($reconciled),
        ]);
    }

    public function cancel(Order $order): Response
    {
        Gate::authorize('view', $order);
        $order->load(['items', 'latestPayment']);

        return Inertia::render('checkout/Result', [
            'outcome' => 'cancelled',
            'order' => $this->presentOrderSummary($order),
        ]);
    }

    public function sandbox(Order $order): Response
    {
        abort_if(app()->isProduction(), 404);
        Gate::authorize('view', $order);

        $order->load(['items', 'latestPayment']);

        return Inertia::render('checkout/Sandbox', [
            'order' => $this->presentOrderSummary($order),
        ]);
    }

    public function simulateSandbox(
        Request $request,
        Order $order,
        ProcessPayMongoWebhookAction $processWebhook,
    ): RedirectResponse {
        abort_if(app()->isProduction(), 404);
        Gate::authorize('view', $order);

        $validated = $request->validate([
            'outcome' => ['required', 'string', 'in:paid,failed,expired'],
        ]);

        /** @var Payment|null $payment */
        $payment = $order->latestPayment()->first();
        $sessionRef = $payment->provider_reference ?? ('cs_sandbox_order_'.$order->id);
        $outcome = (string) $validated['outcome'];

        $eventType = match ($outcome) {
            'paid' => 'checkout_session.payment.paid',
            'failed' => 'payment.failed',
            default => 'checkout_session.expired',
        };

        $processWebhook->execute([
            'data' => [
                'id' => 'evt_sandbox_'.Str::lower(Str::random(16)),
                'type' => 'event',
                'attributes' => [
                    'type' => $eventType,
                    'data' => [
                        'id' => $sessionRef,
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $order->order_number,
                            'status' => $outcome,
                            'metadata' => [
                                'order_id' => (string) $order->id,
                                'payment_id' => $payment !== null ? (string) $payment->id : null,
                            ],
                            'payments' => $outcome === 'paid'
                                ? [[
                                    'id' => 'pay_sandbox_'.Str::lower(Str::random(14)),
                                    'attributes' => [
                                        'status' => 'paid',
                                        'source' => ['type' => 'gcash'],
                                    ],
                                ]]
                                : [],
                        ],
                    ],
                ],
            ],
        ]);

        return redirect()->route($outcome === 'paid' ? 'checkout.success' : 'checkout.cancel', ['order' => $order->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrderSummary(Order $order): array
    {
        $latestPayment = $order->latestPayment;

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'payment_status_label' => $order->payment_status->label(),
            'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
            'payment_method_type' => $latestPayment?->payment_method_type,
            'shipping_recipient_name' => $order->shipping_recipient_name,
            'shipping_address' => $order->formattedShippingAddress(),
            'placed_at_diff' => $order->placed_at->diffForHumans(),
            'items' => $order->items->map(static fn (OrderItem $item): array => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'variant_title' => $item->variant_title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price_formatted' => MoneyFormatter::format($item->unit_price, $item->currency),
                'line_total_formatted' => MoneyFormatter::format($item->line_total, $item->currency),
            ])->values()->all(),
        ];
    }
}
