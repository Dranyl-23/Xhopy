<?php

namespace App\Http\Middleware;

use App\Domain\Seller\Models\SellerProfile;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedSeller
{
    /**
     * Require an authenticated user with an approved Seller profile and active Store.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSeller()) {
            abort(403, 'Seller workspace access is restricted to registered seller accounts.');
        }

        if ($user->hasApprovedStore()) {
            return $next($request);
        }

        if (! $request->isMethodSafe() || $request->expectsJson()) {
            abort(403, 'Seller approval and an active store are required before performing seller actions.');
        }

        $profile = $user->sellerProfile;
        $statusLabel = $profile instanceof SellerProfile ? $profile->status->label() : 'Pending';

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Your seller workspace is locked while your store status is :status.', [
                'status' => $statusLabel,
            ]),
        ]);

        return redirect()->route('seller.onboarding');
    }
}
