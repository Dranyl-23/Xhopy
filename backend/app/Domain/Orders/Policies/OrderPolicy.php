<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->canManageOrders();
    }

    public function manage(User $user): bool
    {
        return $user->canManageOrders();
    }

    public function refund(User $user): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($order->user_id !== $user->id && ! $user->isAdmin()) {
            return false;
        }

        return $order->status === OrderStatus::PendingPayment;
    }
}
