<?php

namespace App\Domain\Cart\Listeners;

use App\Domain\Cart\Services\CartService;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! app()->bound('request')) {
            return;
        }

        $request = request();
        if (! $request->hasSession()) {
            return;
        }

        $sessionToken = $this->cartService->sessionToken($request, false);
        if ($sessionToken !== null) {
            $this->cartService->mergeGuestCartIntoUser($event->user, $sessionToken);
        }
    }
}
