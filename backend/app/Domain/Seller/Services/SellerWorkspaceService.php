<?php

namespace App\Domain\Seller\Services;

use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Models\User;

final class SellerWorkspaceService
{
    /**
     * Resolve the authenticated user's approved Seller profile and active Store, or abort with 403.
     *
     * @return array{profile: SellerProfile, store: Store}
     */
    public function requireApprovedWorkspace(?User $user): array
    {
        if (! $user instanceof User) {
            abort(403, 'Seller workspace access requires authentication.');
        }

        $store = $user->approvedStore();
        $profile = $user->sellerProfile()->first();

        if (! $profile instanceof SellerProfile || ! $store instanceof Store) {
            abort(403, 'Seller approval and an active store are required before performing seller actions.');
        }

        return [
            'profile' => $profile,
            'store' => $store,
        ];
    }
}
