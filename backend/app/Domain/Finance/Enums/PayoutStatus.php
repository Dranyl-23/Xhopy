<?php

namespace App\Domain\Finance\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Approved => 'Approved (Awaiting Transfer)',
            self::Paid => 'Paid / Disbursed',
            self::Rejected => 'Rejected',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Approved, self::Rejected], true),
            self::Approved => in_array($next, [self::Paid, self::Rejected], true),
            self::Paid, self::Rejected => false,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedAdminTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Paid, self::Rejected],
            self::Paid, self::Rejected => [],
        };
    }
}
