<?php

use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/paymongo', PayMongoWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.paymongo');
