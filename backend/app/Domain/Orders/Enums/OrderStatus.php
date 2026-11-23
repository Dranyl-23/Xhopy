<?php

namespace App\Domain\Orders\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return match ($this) {
            self::PendingPayment => in_array($next, [self::Paid, self::Cancelled, self::Expired], true),
            self::Paid => in_array($next, [self::Processing, self::Refunded], true),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled, self::Refunded], true),
            self::Shipped => in_array($next, [self::Delivered, self::Refunded], true),
            self::Delivered => $next === self::Refunded,
            self::Cancelled, self::Expired, self::Refunded => false,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedAdminTransitions(bool $canRefund = false): array
    {
        return match ($this) {
            self::PendingPayment => [self::Cancelled, self::Expired],
            self::Paid => $canRefund ? [self::Processing, self::Refunded] : [self::Processing],
            self::Processing => $canRefund
                ? [self::Shipped, self::Cancelled, self::Refunded]
                : [self::Shipped, self::Cancelled],
            self::Shipped => $canRefund ? [self::Delivered, self::Refunded] : [self::Delivered],
            self::Delivered => $canRefund ? [self::Refunded] : [],
            self::Cancelled, self::Expired, self::Refunded => [],
        };
    }

    /**
     * Approved seller fulfillment transitions per owner Q4 rules:
     * post-payment processing -> shipped -> delivered only; cancellations and refunds remain Admin-governed.
     *
     * @return list<self>
     */
    public function allowedSellerTransitions(): array
    {
        return match ($this) {
            self::Paid => [self::Processing],
            self::Processing => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::PendingPayment, self::Delivered, self::Cancelled, self::Expired, self::Refunded => [],
        };
    }

    public function canSellerTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedSellerTransitions(), true);
    }

    public function releasesReservedStockWhenTransitionedTo(self $next): bool
    {
        return ($this === self::PendingPayment && in_array($next, [self::Cancelled, self::Expired], true))
            || ($this === self::Processing && $next === self::Cancelled);
    }

    public function isRevenueConfirmed(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::Shipped, self::Delivered], true);
    }
}
