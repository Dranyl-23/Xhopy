<?php

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CampaignContentController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Middleware\EnsurePrivilegedMfa;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-catalog', EnsurePrivilegedMfa::class])
    ->prefix('admin/catalog')
    ->name('admin.catalog.')
    ->group(function (): void {
        Route::get('/', [AdminCatalogController::class, 'index'])->name('index');
        Route::get('/products/create', [AdminCatalogController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminCatalogController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [AdminCatalogController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [AdminCatalogController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [AdminCatalogController::class, 'destroy'])->name('products.destroy');

        Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::patch('/products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary'])->name('products.images.primary');
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::patch('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('/campaigns', [CampaignContentController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [CampaignContentController::class, 'store'])->name('campaigns.store');
        Route::patch('/campaigns/{campaign}', [CampaignContentController::class, 'update'])->name('campaigns.update');
        Route::delete('/campaigns/{campaign}', [CampaignContentController::class, 'destroy'])->name('campaigns.destroy');
    });

Route::middleware(['auth', 'verified', 'can:manage-users', EnsurePrivilegedMfa::class])
    ->prefix('admin/users')
    ->name('admin.users.')
    ->group(function (): void {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::patch('/{user}/role', [AdminUserController::class, 'updateRole'])
            ->whereNumber('user')
            ->name('role.update');
    });

Route::middleware(['auth', 'verified', 'can:manage-operations', EnsurePrivilegedMfa::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [AdminOrderController::class, 'index'])->name('index');
            Route::post('/bulk-transition', [AdminOrderController::class, 'bulkTransition'])->name('bulk-transition');
            Route::get('/{order}', [AdminOrderController::class, 'show'])
                ->whereNumber('order')
                ->name('show');
            Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])
                ->whereNumber('order')
                ->name('status.update');
        });

        Route::prefix('inventory')->name('inventory.')->group(function (): void {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::post('/adjust', [InventoryController::class, 'adjust'])->name('adjust');
            Route::post('/import', [InventoryController::class, 'importCsv'])->name('import');
            Route::get('/template', [InventoryController::class, 'template'])->name('template');
        });
    });
