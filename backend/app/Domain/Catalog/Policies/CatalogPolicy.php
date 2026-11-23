<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Product;
use App\Domain\Seller\Models\Store;
use App\Models\User;

class CatalogPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function viewDraft(User $user, ?Product $product = null): bool
    {
        if ($user->canManageCatalog()) {
            return true;
        }

        return $product instanceof Product && $this->manageOwned($user, $product);
    }

    public function manageOwned(User $user, Product $product): bool
    {
        if (! $user->hasApprovedStore()) {
            return false;
        }

        $store = $user->primaryStore();

        return $store instanceof Store
            && $store->isActive()
            && $product->store_id === $store->id;
    }

    public function viewMedia(User $user, Product $product): bool
    {
        return $user->canManageCatalog() || $this->manageOwned($user, $product);
    }
}
