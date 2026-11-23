<?php

namespace App\Domain\Seller\Enums;

enum SellerStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }

    public function canBeEditedByApplicant(): bool
    {
        return in_array($this, [self::Pending, self::Rejected], true);
    }

    public function isOperatingAllowed(): bool
    {
        return $this === self::Approved;
    }

    public function canTransitionAdminTo(self $next, bool $isSuperAdmin): bool
    {
        if ($this === $next) {
            return false;
        }

        if ($next === self::UnderReview) {
            return $this === self::Pending;
        }

        if (! $isSuperAdmin) {
            return false;
        }

        return match ($this) {
            self::Pending, self::UnderReview => in_array($next, [self::Approved, self::Rejected], true),
            self::Approved => $next === self::Suspended,
            self::Suspended => $next === self::Approved,
            self::Rejected => false,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedAdminTransitions(bool $isSuperAdmin): array
    {
        $candidates = [self::UnderReview, self::Approved, self::Rejected, self::Suspended];

        return array_values(array_filter(
            $candidates,
            fn (self $next): bool => $this->canTransitionAdminTo($next, $isSuperAdmin),
        ));
    }
}
