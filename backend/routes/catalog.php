<?php

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\CatalogMediaController;
use App\Http\Controllers\Catalog\ProductDetailController;
use App\Http\Controllers\Catalog\StorefrontStoreController;
use Illuminate\Support\Facades\Route;

Route::get('/catalog', [CatalogController::class, 'index'])
    ->name('catalog.index');

Route::get('/stores', [StorefrontStoreController::class, 'index'])
    ->name('stores.index');

Route::get('/stores/{store:slug}', [StorefrontStoreController::class, 'show'])
    ->where('store', '[A-Za-z0-9\-]+')
    ->name('stores.show');

Route::get('/products/{product}', [ProductDetailController::class, 'show'])
    ->where('product', '[A-Za-z0-9\-]+')
    ->name('products.show');

Route::get('/media/catalog/{productImage}', [CatalogMediaController::class, 'show'])
    ->whereNumber('productImage')
    ->name('catalog.media.show');
