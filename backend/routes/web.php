<?php

use App\Http\Controllers\Account\AccountDashboardController;
use App\Http\Controllers\Storefront\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', AccountDashboardController::class)->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/catalog.php';
require __DIR__.'/commerce.php';
require __DIR__.'/seller.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
