<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Finance\Enums\LedgerEntryType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\CommissionEntry;
use App\Domain\Finance\Models\CommissionRule;
use App\Domain\Finance\Models\SellerLedgerEntry;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Finance\Services\SellerFinanceService;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\StaffPermission;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\ProcessPayMongoWebhookAction;
use App\Domain\Payments\Models\Payment;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{user: User, profile: SellerProfile, store: Store}
 */
function createMv6SellerWorkspace(string $name, string $slug): array
{
    $user = User::factory()->seller()->create();
    $store = Store::query()->create([
        'owner_user_id' => $user->id,
        'name' => $name,
        'slug' => $slug,
        'description' => "{$name} verified merchant store.",
        'contact_email' => "{$slug}@xhopy.example",
        'contact_phone' => '+63 917 555 0200',
        'status' => StoreStatus::Active,
        'is_official' => false,
    ]);

    StoreMembership::query()->create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    $profile = SellerProfile::query()->create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'business_name' => "{$name} Corp",
        'business_type' => 'sole_proprietor',
        'contact_phone' => '+63 917 555 0200',
        'business_address' => '75 Paseo de Roxas, Makati',
        'status' => SellerStatus::Approved,
        'submitted_at' => now()->subDays(3),
        'reviewed_at' => now()->subDays(2),
        'approved_at' => now()->subDays(2),
    ]);

    return compact('user', 'profile', 'store');
}

function settleOrderViaPayMongoWebhook(Order $order, string $eventId, string $paymentId): void
{
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    app(ProcessPayMongoWebhookAction::class)->execute([
        'data' => [
            'id' => $eventId,
            'attributes' => [
                'type' => 'payment.paid',
                'data' => [
                    'id' => $paymentId,
                    'attributes' => [
                        'source' => ['type' => 'gcash'],
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'payment_id' => (string) $payment->id,
                        ],
                    ],
                ],
            ],
        ],
    ]);
}

test('commission snapshots apply 5 percent seller and 0 percent official rates and never mutate retroactively', function (): void {
    Notification::fake();
    config(['auth.require_privileged_mfa' => false, 'services.paymongo.secret_key' => '']);

    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $superAdmin = User::factory()->admin()->create();

    $officialStore = Store::query()->where('slug', 'xhopy-official')->firstOrFail();
    ['store' => $coffeeStore] = createMv6SellerWorkspace('Cordillera Coffee', 'cordillera-coffee');

    $officialKeyboard = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $officialStore->id,
        'seller_name' => $officialStore->name,
        'name' => 'XHopy Mechanical Keycap Set',
        'base_price' => '3200.00',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $beans = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $coffeeStore->id,
        'seller_name' => $coffeeStore->name,
        'name' => 'Sagada Whole Bean 1kg',
        'base_price' => '1000.00',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $officialKeyboard->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $beans->id, 'quantity' => 2]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'idempotency_key' => 'mv6-commission-01',
    ])->assertRedirect();

    $orderOne = Order::query()->with('sellerOrders')->firstOrFail();
    settleOrderViaPayMongoWebhook($orderOne, 'evt_mv6_comm_01', 'pay_mv6_comm_01');
    // Replaying the same webhook must be strictly idempotent for commission entries and ledger credits.
    settleOrderViaPayMongoWebhook($orderOne, 'evt_mv6_comm_01', 'pay_mv6_comm_01');

    $officialSuborder = $orderOne->sellerOrders->firstWhere('store_id', $officialStore->id)?->fresh();
    $coffeeSuborder = $orderOne->sellerOrders->firstWhere('store_id', $coffeeStore->id)?->fresh();

    expect($officialSuborder)->not->toBeNull()
        ->and($coffeeSuborder)->not->toBeNull()
        ->and($officialSuborder->commission_rate_bps)->toBe(0)
        ->and((string) $officialSuborder->commission_amount)->toBe('0.00')
        ->and((string) $officialSuborder->net_seller_amount)->toBe('3200.00')
        ->and($coffeeSuborder->commission_rate_bps)->toBe(500)
        ->and((string) $coffeeSuborder->commission_amount)->toBe('100.00')
        ->and((string) $coffeeSuborder->net_seller_amount)->toBe('1900.00')
        ->and(CommissionEntry::query()->count())->toBe(2)
        ->and(SellerLedgerEntry::query()->where('store_id', $coffeeStore->id)->where('entry_type', LedgerEntryType::OrderEarning->value)->count())->toBe(1)
        ->and(SellerLedgerEntry::query()->where('store_id', $coffeeStore->id)->sum('amount_centavos'))->toBe(190000);

    // Super Admin raises the global commission rate to 8% with a 10-day holding period.
    $this->actingAs($superAdmin)
        ->post(route('admin.finance.commission-rules.store'), [
            'store_id' => null,
            'rate_percent' => 8.0,
            'hold_days' => 10,
            'minimum_payout_amount' => 500,
            'notes' => 'Seasonal marketplace rate adjustment',
        ])
        ->assertRedirect();

    expect(CommissionRule::query()->whereNull('store_id')->where('is_active', true)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::AdminCommissionRuleUpdated->value)->count())->toBe(1);

    // Historical settled order remains locked at 5%.
    expect($coffeeSuborder->fresh()?->commission_rate_bps)->toBe(500)
        ->and((string) $coffeeSuborder->fresh()?->commission_amount)->toBe('100.00');

    // A second order uses the new 8% rule while xhopy-official remains 0%.
    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $beans->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'idempotency_key' => 'mv6-commission-02',
    ])->assertRedirect();

    $orderTwo = Order::query()->where('id', '!=', $orderOne->id)->with('sellerOrders')->firstOrFail();
    settleOrderViaPayMongoWebhook($orderTwo, 'evt_mv6_comm_02', 'pay_mv6_comm_02');

    $secondCoffeeSuborder = $orderTwo->sellerOrders->firstWhere('store_id', $coffeeStore->id)?->fresh();
    expect($secondCoffeeSuborder)->not->toBeNull()
        ->and($secondCoffeeSuborder->commission_rate_bps)->toBe(800)
        ->and((string) $secondCoffeeSuborder->commission_amount)->toBe('80.00')
        ->and((string) $secondCoffeeSuborder->net_seller_amount)->toBe('920.00');
});

test('seven-day hold, minimum payout threshold, overdraft lock, and super-admin rbac enforce end-to-end finance reconciliation', function (): void {
    Notification::fake();
    config(['auth.require_privileged_mfa' => false, 'services.paymongo.secret_key' => '']);

    $baseTime = CarbonImmutable::parse('2026-11-24 09:00:00', 'UTC');
    CarbonImmutable::setTestNow($baseTime);

    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $staffUser = User::factory()->staff([
        StaffPermission::OrdersManage->value,
        StaffPermission::CatalogManage->value,
        StaffPermission::SellersReview->value,
    ])->create();
    $superAdmin = User::factory()->admin()->create();

    ['user' => $sellerA, 'store' => $storeA] = createMv6SellerWorkspace('Mayon Roasters', 'mayon-roasters');
    ['user' => $sellerB] = createMv6SellerWorkspace('Taal Goods', 'taal-goods');

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'name' => 'Mayon Espresso Kit',
        'base_price' => '2000.00',
        'stock_quantity' => 5,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'idempotency_key' => 'mv6-payout-flow-01',
    ])->assertRedirect();

    $order = Order::query()->with('sellerOrders')->firstOrFail();
    settleOrderViaPayMongoWebhook($order, 'evt_mv6_payout_01', 'pay_mv6_payout_01');

    $suborder = $order->sellerOrders->firstWhere('store_id', $storeA->id)?->fresh();
    expect($suborder)->not->toBeNull();

    // Immediately after Paid: PHP 1,900.00 sits in fulfillment, 0 on hold, 0 available.
    $financeService = app(SellerFinanceService::class);
    $balancesPaid = $financeService->balancesForStore($storeA);
    expect($balancesPaid['inFulfillmentCentavos'])->toBe(190000)
        ->and($balancesPaid['onHoldCentavos'])->toBe(0)
        ->and($balancesPaid['availableCentavos'])->toBe(0);

    // Advance suborder through Delivered at baseTime.
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborder), ['status' => 'processing'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborder), ['status' => 'shipped'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborder), ['status' => 'delivered'])->assertRedirect();

    $suborder->refresh();
    expect($suborder->status)->toBe(OrderStatus::Delivered)
        ->and($suborder->hold_until)->not->toBeNull()
        ->and($suborder->hold_until?->equalTo($baseTime->addDays(7)))->toBeTrue();

    // At +3 days, net earnings remain in the 7-day hold bucket; payout requests must be rejected.
    CarbonImmutable::setTestNow($baseTime->addDays(3));
    $balancesHeld = $financeService->balancesForStore($storeA);
    expect($balancesHeld['inFulfillmentCentavos'])->toBe(0)
        ->and($balancesHeld['onHoldCentavos'])->toBe(190000)
        ->and($balancesHeld['availableCentavos'])->toBe(0);

    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '600.00',
            'payout_method' => 'bank_transfer',
            'payout_destination' => 'BPI Account ****4421',
        ])
        ->assertSessionHasErrors('amount');

    // At +8 days, the 7-day hold clears and PHP 1,900.00 moves into Available Balance.
    CarbonImmutable::setTestNow($baseTime->addDays(8));
    $this->actingAs($sellerA)
        ->get(route('seller.finance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/Finance')
            ->where('balances.availableCentavos', 190000)
            ->where('balances.onHoldCentavos', 0));

    // Below PHP 500.00 minimum withdrawal must fail.
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '499.00',
            'payout_method' => 'gcash',
            'payout_destination' => '09171234567',
        ])
        ->assertSessionHasErrors('amount');

    // Above available balance (PHP 1,901.00) must fail.
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '1901.00',
            'payout_method' => 'gcash',
            'payout_destination' => '09171234567',
        ])
        ->assertSessionHasErrors('amount');

    // Valid withdrawal of PHP 1,200.00 succeeds and leaves PHP 700.00 available.
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '1200.00',
            'payout_method' => 'bank_transfer',
            'payout_destination' => 'UnionBank ****9012',
            'request_notes' => 'Weekly batch withdrawal',
        ])
        ->assertRedirect();

    /** @var SellerPayout $payoutOne */
    $payoutOne = SellerPayout::query()->where('store_id', $storeA->id)->firstOrFail();
    expect($payoutOne->status)->toBe(PayoutStatus::Pending)
        ->and($payoutOne->amount_centavos)->toBe(120000)
        ->and(SellerLedgerEntry::query()->where('seller_payout_id', $payoutOne->id)->where('entry_type', LedgerEntryType::PayoutDebit->value)->value('amount_centavos'))->toBe(-120000)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::SellerPayoutRequested->value)->count())->toBe(1);

    $afterRequestBalances = $financeService->balancesForStore($storeA);
    expect($afterRequestBalances['availableCentavos'])->toBe(70000)
        ->and($afterRequestBalances['pendingPayoutCentavos'])->toBe(120000);

    // Concurrent/second withdrawal exceeding remaining PHP 700.00 must be blocked.
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '750.00',
            'payout_method' => 'gcash',
            'payout_destination' => '09171234567',
        ])
        ->assertSessionHasErrors('amount');

    // Admin staff without Super Admin cannot access admin finance governance or mutate payouts.
    $this->actingAs($staffUser)->get(route('admin.finance.index'))->assertForbidden();
    $this->actingAs($staffUser)->post(route('admin.finance.commission-rules.store'), [
        'rate_percent' => 10,
        'hold_days' => 5,
        'minimum_payout_amount' => 500,
    ])->assertForbidden();
    $this->actingAs($staffUser)->patch(route('admin.finance.payouts.update', $payoutOne), [
        'status' => 'approved',
    ])->assertForbidden();
    $this->actingAs($sellerB)->get(route('admin.finance.index'))->assertForbidden();

    // Super Admin sees payout queue on /admin/finance, approves, then marks disbursed with bank transfer ref.
    $this->actingAs($superAdmin)
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Finance')
            ->where('kpis.pendingPayoutCount', 1)
            ->where('payouts.data.0.payout_number', $payoutOne->payout_number));

    $this->actingAs($superAdmin)
        ->patch(route('admin.finance.payouts.update', $payoutOne), [
            'status' => 'approved',
            'review_notes' => 'Account verified',
        ])
        ->assertRedirect();

    $this->actingAs($superAdmin)
        ->patch(route('admin.finance.payouts.update', $payoutOne), [
            'status' => 'paid',
            'external_reference' => 'PESONET-20261202-771',
            'review_notes' => 'Disbursed to UnionBank',
        ])
        ->assertRedirect();

    $payoutOne->refresh();
    expect($payoutOne->status)->toBe(PayoutStatus::Paid)
        ->and($payoutOne->external_reference)->toBe('PESONET-20261202-771')
        ->and($payoutOne->paid_at)->not->toBeNull();

    $afterDisbursementBalances = $financeService->balancesForStore($storeA);
    expect($afterDisbursementBalances['availableCentavos'])->toBe(70000)
        ->and($afterDisbursementBalances['pendingPayoutCentavos'])->toBe(0)
        ->and($afterDisbursementBalances['paidOutCentavos'])->toBe(120000);

    // Seller requests remaining PHP 700.00; Super Admin rejection credits funds back cleanly.
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '700.00',
            'payout_method' => 'maya',
            'payout_destination' => '09980001122',
        ])
        ->assertRedirect();

    $payoutTwo = SellerPayout::query()->where('store_id', $storeA->id)->latest('id')->firstOrFail();

    // Rejecting without review_notes triggers validation error.
    $this->actingAs($superAdmin)
        ->patch(route('admin.finance.payouts.update', $payoutTwo), [
            'status' => 'rejected',
            'review_notes' => '',
        ])
        ->assertSessionHasErrors('review_notes');

    $this->actingAs($superAdmin)
        ->patch(route('admin.finance.payouts.update', $payoutTwo), [
            'status' => 'rejected',
            'review_notes' => 'Maya destination account mismatched',
        ])
        ->assertRedirect();

    expect($payoutTwo->fresh()?->status)->toBe(PayoutStatus::Rejected)
        ->and($financeService->balancesForStore($storeA)['availableCentavos'])->toBe(70000)
        ->and(SellerLedgerEntry::query()->where('seller_payout_id', $payoutTwo->id)->where('entry_type', LedgerEntryType::PayoutRejectionCredit->value)->value('amount_centavos'))->toBe(70000);
});

test('post-payment cancellations and refunds append compensating ledger reversals cleanly without corrupting sibling stores', function (): void {
    Notification::fake();
    config(['auth.require_privileged_mfa' => false, 'services.paymongo.secret_key' => '']);

    $baseTime = CarbonImmutable::parse('2026-11-24 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($baseTime);

    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $superAdmin = User::factory()->admin()->create();

    ['user' => $sellerA, 'store' => $storeA] = createMv6SellerWorkspace('Cebu Ceramics', 'cebu-ceramics');
    ['store' => $storeB] = createMv6SellerWorkspace('Davao Cacao', 'davao-cacao');

    $ceramicSet = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'base_price' => '2000.00',
        'stock_quantity' => 5,
        'status' => ProductStatus::Active,
    ]);

    $cacaoBars = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'base_price' => '1000.00',
        'stock_quantity' => 5,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $ceramicSet->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $cacaoBars->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'idempotency_key' => 'mv6-reversal-01',
    ])->assertRedirect();

    $order = Order::query()->with('sellerOrders')->firstOrFail();
    settleOrderViaPayMongoWebhook($order, 'evt_mv6_rev_01', 'pay_mv6_rev_01');

    $suborderA = $order->sellerOrders->firstWhere('store_id', $storeA->id)?->fresh();
    $suborderB = $order->sellerOrders->firstWhere('store_id', $storeB->id)?->fresh();
    $financeService = app(SellerFinanceService::class);

    // Store A fulfills and withdraws PHP 1,500.00 after the 7-day hold.
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'processing'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'shipped'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'delivered'])->assertRedirect();

    CarbonImmutable::setTestNow($baseTime->addDays(8));
    $this->actingAs($sellerA)
        ->post(route('seller.finance.payouts.store'), [
            'amount' => '1500.00',
            'payout_method' => 'bank_transfer',
            'payout_destination' => 'BDO ****3321',
        ])
        ->assertRedirect();

    // Super Admin moves Store B to Processing then cancels prior to dispatch -> Store B earnings reverse cleanly to 0.
    $this->actingAs($superAdmin)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderB]), [
            'status' => 'processing',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($superAdmin)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderB]), [
            'status' => 'cancelled',
            'reason' => 'Merchant supply outage',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $entryB = CommissionEntry::query()->where('seller_order_id', $suborderB->id)->firstOrFail();
    expect($entryB->status)->toBe('reversed')
        ->and($entryB->reversed_at)->not->toBeNull()
        ->and(SellerLedgerEntry::query()->where('store_id', $storeB->id)->sum('amount_centavos'))->toBe(0)
        ->and($financeService->balancesForStore($storeB)['inFulfillmentCentavos'])->toBe(0);

    // Super Admin approves a post-payout refund on Store A -> negative clawback deficit (-150000 centavos) recorded accurately.
    $this->actingAs($superAdmin)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderA]), [
            'status' => 'refunded',
            'reason' => 'Defective glaze claim approved',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $entryA = CommissionEntry::query()->where('seller_order_id', $suborderA->id)->firstOrFail();
    $balancesA = $financeService->balancesForStore($storeA);
    expect($entryA->status)->toBe('reversed')
        ->and(SellerLedgerEntry::query()->where('seller_order_id', $suborderA->id)->where('entry_type', LedgerEntryType::OrderReversal->value)->value('amount_centavos'))->toBe(-190000)
        ->and($balancesA['maturedCentavos'])->toBe(0)
        ->and($balancesA['availableCentavos'])->toBe(-150000);
});
