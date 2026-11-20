<?php

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\CatalogMediaController;
use App\Http\Controllers\Catalog\ProductDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/catalog', [CatalogController::class, 'index'])
    ->name('catalog.index');

Route::get('/products/{product}', [ProductDetailController::class, 'show'])
    ->where('product', '[A-Za-z0-9\-]+')
    ->name('products.show');

Route::get('/media/catalog/{productImage}', [CatalogMediaController::class, 'show'])
    ->whereNumber('productImage')
    ->name('catalog.media.show');
