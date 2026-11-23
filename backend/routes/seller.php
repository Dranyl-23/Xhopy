<?php

use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\InventoryController;
use App\Http\Controllers\Seller\OnboardingController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\StoreProfileController;
use App\Http\Middleware\EnsureApprovedSeller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('seller')
    ->name('seller.')
    ->group(function (): void {
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
        Route::post('/onboarding/documents', [OnboardingController::class, 'uploadDocument'])
            ->name('onboarding.documents.store');
        Route::get('/onboarding/documents/{document}', [OnboardingController::class, 'downloadDocument'])
            ->whereNumber('document')
            ->name('onboarding.documents.download');
        Route::delete('/onboarding/documents/{document}', [OnboardingController::class, 'destroyDocument'])
            ->whereNumber('document')
            ->name('onboarding.documents.destroy');

        Route::middleware([EnsureApprovedSeller::class])->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');

            Route::get('/store', [StoreProfileController::class, 'edit'])->name('store.edit');
            Route::patch('/store', [StoreProfileController::class, 'update'])->name('store.update');

            Route::prefix('products')->name('products.')->group(function (): void {
                Route::get('/', [ProductController::class, 'index'])->name('index');
                Route::get('/create', [ProductController::class, 'create'])->name('create');
                Route::post('/', [ProductController::class, 'store'])->name('store');
                Route::get('/{product}/edit', [ProductController::class, 'edit'])
                    ->whereNumber('product')
                    ->name('edit');
                Route::patch('/{product}', [ProductController::class, 'update'])
                    ->whereNumber('product')
                    ->name('update');
                Route::delete('/{product}', [ProductController::class, 'destroy'])
                    ->whereNumber('product')
                    ->name('destroy');

                Route::post('/{product}/images', [ProductImageController::class, 'store'])
                    ->whereNumber('product')
                    ->name('images.store');
                Route::patch('/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary'])
                    ->whereNumber(['product', 'image'])
                    ->name('images.primary');
                Route::delete('/{product}/images/{image}', [ProductImageController::class, 'destroy'])
                    ->whereNumber(['product', 'image'])
                    ->name('images.destroy');
            });

            Route::prefix('inventory')->name('inventory.')->group(function (): void {
                Route::get('/', [InventoryController::class, 'index'])->name('index');
                Route::post('/adjust', [InventoryController::class, 'adjust'])->name('adjust');
                Route::post('/import', [InventoryController::class, 'importCsv'])->name('import');
                Route::get('/template', [InventoryController::class, 'template'])->name('template');
            });
        });
    });
