<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Models\User;

class SellerOrderPolicy
{
    /**
     * Only an approved seller belonging to the matching active store may inspect a seller suborder.
     */
    public function viewOwned(User $user, SellerOrder $sellerOrder): bool
    {
        $store = $user->approvedStore();
        if (! $store instanceof Store) {
            return false;
        }

        $profile = $user->sellerProfile()->first();
        if (! $profile instanceof SellerProfile || $profile->status !== SellerStatus::Approved) {
            return false;
        }

        return (int) $sellerOrder->store_id === (int) $store->id;
    }

    /**
     * Approved active store owners may advance post-payment fulfillment for their own suborder only.
     */
    public function fulfillOwned(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->viewOwned($user, $sellerOrder);
    }
}
