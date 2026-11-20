<?php

use App\Http\Controllers\Auth\EmailVerificationCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('email/verification-code', [EmailVerificationCodeController::class, 'store'])
        ->middleware('throttle:verification-code')
        ->name('verification.code.send');

    Route::post('email/verification-code/verify', [EmailVerificationCodeController::class, 'verify'])
        ->middleware('throttle:verification-code-verify')
        ->name('verification.code.verify');
});
