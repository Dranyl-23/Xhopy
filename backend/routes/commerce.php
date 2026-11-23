<?php

use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Customer\WishlistController;
use App\Http\Controllers\Orders\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('cart.items.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])
    ->whereNumber('cartItem')
    ->name('cart.items.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])
    ->whereNumber('cartItem')
    ->name('cart.items.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');

    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])
        ->whereNumber('order')
        ->name('checkout.success');
    Route::get('/checkout/cancel/{order}', [CheckoutController::class, 'cancel'])
        ->whereNumber('order')
        ->name('checkout.cancel');

    Route::get('/checkout/sandbox/{order}', [CheckoutController::class, 'sandbox'])
        ->whereNumber('order')
        ->name('checkout.sandbox');
    Route::post('/checkout/sandbox/{order}/simulate', [CheckoutController::class, 'simulateSandbox'])
        ->whereNumber('order')
        ->name('checkout.sandbox.simulate');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/notifications/read', [OrderController::class, 'markNotificationsRead'])->name('orders.notifications.read');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->whereNumber('order')
        ->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->whereNumber('order')
        ->name('orders.cancel');
    Route::post('/orders/{order}/retry-payment', [OrderController::class, 'retryPayment'])
        ->whereNumber('order')
        ->name('orders.retry-payment');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}/toggle', [WishlistController::class, 'toggle'])
        ->whereNumber('product')
        ->name('wishlist.toggle');
    Route::delete('/wishlist/{wishlistItem}', [WishlistController::class, 'destroy'])
        ->whereNumber('wishlistItem')
        ->name('wishlist.destroy');
});
