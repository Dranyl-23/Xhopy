<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest can add products to persistent cart and see live summary across storefront and cart page', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Acoustic Studio Monitor',
        'status' => ProductStatus::Active,
        'base_price' => '1500.00',
        'stock_quantity' => 8,
    ]);

    $this->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.itemCount', 2)
            ->where('cartSummary.subtotalFormatted', '₱3,000.00')
            ->has('cartSummary.items', 1)
        );

    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/Index')
            ->where('cart.itemCount', 2)
            ->where('cart.subtotalFormatted', '₱3,000.00')
            ->where('cart.items.0.productName', 'Acoustic Studio Monitor')
        );
});

test('customer can add variant item, update quantity within stock limits, remove item, and clear cart', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '2000.00',
        'stock_quantity' => 0,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'title' => 'Space Black',
        'price' => '2250.00',
        'stock_quantity' => 4,
        'is_active' => true,
        'is_default' => true,
    ]);
    $secondProduct = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'base_price' => '500.00',
        'stock_quantity' => 5,
    ]);

    $this->actingAs($user)
        ->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();
    $item = CartItem::query()->where('cart_id', $cart->id)->firstOrFail();

    expect($item->quantity)->toBe(2)
        ->and($item->product_variant_id)->toBe($variant->id);

    // Exceeding stock limit fails validation without mutating quantity
    $this->actingAs($user)
        ->patch(route('cart.items.update', ['cartItem' => $item->id]), [
            'quantity' => 5,
        ])
        ->assertSessionHasErrors('quantity');

    expect($item->fresh()?->quantity)->toBe(2);

    // Valid quantity update succeeds
    $this->actingAs($user)
        ->patch(route('cart.items.update', ['cartItem' => $item->id]), [
            'quantity' => 3,
        ])
        ->assertRedirect();

    expect($item->fresh()?->quantity)->toBe(3);

    // Add second product then remove first and clear
    $this->actingAs($user)
        ->post(route('cart.items.store'), [
            'product_id' => $secondProduct->id,
            'quantity' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('cart.items.destroy', ['cartItem' => $item->id]))
        ->assertRedirect();

    expect($cart->items()->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('cart.clear'))
        ->assertRedirect();

    expect($cart->items()->count())->toBe(0);
});

test('mismatched variant and product combination is rejected', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $productA = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);
    $productB = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);
    $variantOnB = ProductVariant::factory()->create([
        'product_id' => $productB->id,
        'stock_quantity' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('cart.items.store'), [
            'product_id' => $productA->id,
            'product_variant_id' => $variantOnB->id,
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('product_variant_id');
});

test('guest cart items merge atomically into authenticated user cart upon login', function () {
    $user = User::factory()->create([
        'password' => 'Batch5!Pass2026',
    ]);
    $category = Category::factory()->create(['is_active' => true]);
    $sharedProduct = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 6,
    ]);
    $guestOnlyProduct = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    $userCart = Cart::query()->create(['user_id' => $user->id, 'currency' => 'PHP']);
    CartItem::query()->create([
        'cart_id' => $userCart->id,
        'product_id' => $sharedProduct->id,
        'product_variant_id' => null,
        'variant_key' => 0,
        'quantity' => 2,
    ]);

    // Guest adds both sharedProduct (qty 2) and guestOnlyProduct (qty 1) in session
    $this->post(route('cart.items.store'), [
        'product_id' => $sharedProduct->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('cart.items.store'), [
        'product_id' => $guestOnlyProduct->id,
        'quantity' => 1,
    ])->assertRedirect();

    // Logging in through Fortify triggers Login event listener MergeGuestCartOnLogin
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Batch5!Pass2026',
    ])->assertRedirect(route('dashboard', absolute: false));

    $userCart->refresh();
    $mergedItems = $userCart->items()->get()->keyBy('product_id');

    expect($mergedItems)->toHaveCount(2)
        ->and($mergedItems[$sharedProduct->id]->quantity)->toBe(4)
        ->and($mergedItems[$guestOnlyProduct->id]->quantity)->toBe(1)
        ->and(Cart::query()->whereNull('user_id')->count())->toBe(0);
});

test('customer cannot modify or delete another users cart items', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
    ]);

    $ownerCart = Cart::query()->create(['user_id' => $owner->id, 'currency' => 'PHP']);
    $ownerItem = CartItem::query()->create([
        'cart_id' => $ownerCart->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
        'variant_key' => 0,
        'quantity' => 2,
    ]);

    $this->actingAs($attacker)
        ->patch(route('cart.items.update', ['cartItem' => $ownerItem->id]), [
            'quantity' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($attacker)
        ->delete(route('cart.items.destroy', ['cartItem' => $ownerItem->id]))
        ->assertForbidden();

    expect($ownerItem->fresh()?->quantity)->toBe(2);
});
