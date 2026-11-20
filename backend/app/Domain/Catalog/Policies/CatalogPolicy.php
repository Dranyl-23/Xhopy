<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Product;
use App\Models\User;

class CatalogPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function viewDraft(User $user, ?Product $product = null): bool
    {
        return $user->canManageCatalog();
    }
}
