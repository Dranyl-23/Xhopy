<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Gateways\PayMongoGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('checkout places idempotent order, snapshots prices, reserves stock atomically, and prevents double deduction on replay', function () {
    config([
        'services.paymongo.secret_key' => '',
    ]);

    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Mechanical Keyboard',
        'status' => ProductStatus::Active,
        'base_price' => '3200.00',
        'stock_quantity' => 5,
    ]);

    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $idempotencyKey = (string) Str::uuid();

    // First submit
    $firstResponse = $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
            'notes' => 'Ring doorbell twice',
        ]);

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();

    $firstResponse->assertRedirect(route('checkout.sandbox', ['order' => $order->id]));

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and((string) $order->total_amount)->toBe('6400.00')
        ->and($order->items()->count())->toBe(1)
        ->and($product->fresh()?->stock_quantity)->toBe(3);

    $cart = Cart::query()->where('user_id', $customer->id)->firstOrFail();
    expect($cart->items()->count())->toBe(0);

    // Mutate catalog price after placement -> existing order snapshot remains immutable
    $product->update(['base_price' => '4999.00']);
    expect((string) $order->items()->firstOrFail()->unit_price)->toBe('3200.00');

    // Second submit with same idempotency_key returns existing order without creating duplicate order or deducting stock again
    $secondResponse = $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ]);

    $secondResponse->assertRedirect(route('checkout.sandbox', ['order' => $order->id]));

    expect(Order::query()->where('user_id', $customer->id)->count())->toBe(1)
        ->and($product->fresh()?->stock_quantity)->toBe(3)
        ->and(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Reserved)->count())->toBe(1);
});

test('checkout fails cleanly when inventory hits zero before checkout completes', function () {
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '1200.00',
        'stock_quantity' => 2,
    ]);

    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    // Concurrent buyer consumes stock down to 0
    $product->update(['stock_quantity' => 0]);

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('cart');

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()?->stock_quantity)->toBe(0);
});

test('paymongo checkout session integration creates provider reference, never trusts success redirect alone, and reconciles via api', function () {
    config([
        'services.paymongo.secret_key' => 'sk_test_fake_123456',
        'services.paymongo.webhook_secret' => 'whsk_test_secret_123456',
        'services.paymongo.mode' => 'test',
        'services.paymongo.base_url' => 'https://api.paymongo.com/v1',
    ]);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_session_001',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_session_001',
                ],
            ],
        ], 200),
        'https://api.paymongo.com/v1/checkout_sessions/cs_test_session_001' => Http::sequence()
            ->push([
                'data' => [
                    'id' => 'cs_test_session_001',
                    'attributes' => [
                        'status' => 'active',
                        'payments' => [],
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    'id' => 'cs_test_session_001',
                    'attributes' => [
                        'status' => 'paid',
                        'payments' => [[
                            'id' => 'pay_pm_live_001',
                            'attributes' => [
                                'status' => 'paid',
                                'source' => ['type' => 'gcash'],
                            ],
                        ]],
                    ],
                ],
            ], 200),
    ]);

    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '750.00',
        'stock_quantity' => 4,
    ]);

    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $response = $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

    $response->assertRedirect('https://checkout.paymongo.com/cs_test_session_001');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && data_get($payload, 'data.attributes.line_items.0.amount') === 75000
            && data_get($payload, 'data.attributes.line_items.0.quantity') === 2;
    });

    $order = Order::query()->firstOrFail();
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
    expect($payment->provider_reference)->toBe('cs_test_session_001');

    // First return to success URL while PayMongo session is still active -> NOT marked paid!
    $this->actingAs($customer)
        ->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.payment_status', 'pending')
            ->where('order.status', 'pending_payment')
        );

    expect($order->fresh()?->status)->toBe(OrderStatus::PendingPayment);

    // Second return when PayMongo API returns paid payment -> reconciled cleanly
    $this->actingAs($customer)
        ->get(route('checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.payment_status', 'paid')
            ->where('order.status', 'paid')
        );

    expect($order->fresh()?->status)->toBe(OrderStatus::Paid)
        ->and($payment->fresh()?->payment_method_type)->toBe('gcash');
});

test('paymongo webhook verifies signatures, processes events idempotently, and releases stock on expiration', function () {
    config([
        'services.paymongo.webhook_secret' => 'whsk_test_webhook_999',
        'services.paymongo.mode' => 'test',
        'services.paymongo.webhook_tolerance_seconds' => 300,
    ]);

    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '900.00',
        'stock_quantity' => 5,
    ]);

    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])
        ->assertRedirect();

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

    $order = Order::query()->firstOrFail();
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    expect($product->fresh()?->stock_quantity)->toBe(2);

    $paidPayload = json_encode([
        'data' => [
            'id' => 'evt_pm_paid_001',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => $payment->provider_reference ?? 'cs_test_ref',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'payment_id' => (string) $payment->id,
                        ],
                        'payments' => [[
                            'id' => 'pay_pm_001',
                            'attributes' => [
                                'status' => 'paid',
                                'source' => ['type' => 'card'],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    // Invalid signature rejected with 401
    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => 't='.time().',te=invalid_hmac,li=',
    ], $paidPayload)->assertUnauthorized();

    expect($order->fresh()?->status)->toBe(OrderStatus::PendingPayment);

    // Valid signature transitions order & payment to Paid
    $validHeader = PayMongoGateway::signPayload($paidPayload, 'whsk_test_webhook_999', time(), 'test');

    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => $validHeader,
    ], $paidPayload)
        ->assertOk()
        ->assertJson([
            'received' => true,
            'status' => 'processed',
        ]);

    expect($order->fresh()?->status)->toBe(OrderStatus::Paid)
        ->and($payment->fresh()?->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()?->payment_method_type)->toBe('card');

    // Duplicate event replay is idempotent
    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => $validHeader,
    ], $paidPayload)
        ->assertOk()
        ->assertJson([
            'received' => true,
            'status' => 'already_processed',
        ]);

    expect(PaymentEvent::query()->where('provider_event_id', 'evt_pm_paid_001')->count())->toBe(1);

    // Second order expires via webhook -> releases stock cleanly
    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

    expect($product->fresh()?->stock_quantity)->toBe(0);

    $secondOrder = Order::query()->where('id', '!=', $order->id)->firstOrFail();
    $secondPayment = Payment::query()->where('order_id', $secondOrder->id)->firstOrFail();

    $expiredPayload = json_encode([
        'data' => [
            'id' => 'evt_pm_expired_001',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.expired',
                'data' => [
                    'id' => $secondPayment->provider_reference ?? 'cs_expired_ref',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'metadata' => [
                            'order_id' => (string) $secondOrder->id,
                            'payment_id' => (string) $secondPayment->id,
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $expiredHeader = PayMongoGateway::signPayload($expiredPayload, 'whsk_test_webhook_999', time(), 'test');

    $this->call('POST', route('webhooks.paymongo'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => $expiredHeader,
    ], $expiredPayload)->assertOk();

    expect($secondOrder->fresh()?->status)->toBe(OrderStatus::Expired)
        ->and($secondPayment->fresh()?->status)->toBe(PaymentStatus::Expired)
        ->and($product->fresh()?->stock_quantity)->toBe(2)
        ->and(StockMovement::query()->where('order_id', $secondOrder->id)->where('type', StockMovementType::Released)->count())->toBe(1);
});

test('cross-customer IDOR is denied on addresses during checkout, orders views, and order cancellations', function () {
    $customer = User::factory()->create();
    $attacker = User::factory()->create();
    $customerAddress = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '500.00',
        'stock_quantity' => 6,
    ]);

    // Attacker puts item in own cart and tries checking out with victim address
    $this->actingAs($attacker)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($attacker)
        ->post(route('checkout.store'), [
            'address_id' => $customerAddress->id,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();

    // Victim places order
    $this->actingAs($customer)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $customerAddress->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();

    // Attacker cannot view, simulate, or cancel victim order
    $this->actingAs($attacker)->get(route('orders.show', ['order' => $order->id]))->assertForbidden();
    $this->actingAs($attacker)->get(route('checkout.success', ['order' => $order->id]))->assertForbidden();
    $this->actingAs($attacker)->post(route('orders.cancel', ['order' => $order->id]))->assertForbidden();

    // Owner cancels pending_payment order -> releases stock cleanly
    $this->actingAs($customer)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()?->stock_quantity)->toBe(6);

    // Cancelling already-cancelled order is rejected by OrderPolicy
    $this->actingAs($customer)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertForbidden();
});
