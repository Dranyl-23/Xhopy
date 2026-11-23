<?php

namespace App\Domain\Customer\Policies;

use App\Domain\Customer\Models\WishlistItem;
use App\Models\User;

class WishlistItemPolicy
{
    public function view(User $user, WishlistItem $wishlistItem): bool
    {
        return $wishlistItem->user_id === $user->id;
    }

    public function delete(User $user, WishlistItem $wishlistItem): bool
    {
        return $wishlistItem->user_id === $user->id;
    }
}
